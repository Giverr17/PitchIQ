<?php

namespace App\Services;

use Prism\Prism\Facades\Prism;
use Prism\Prism\Enums\Provider;
use Prism\Prism\Exceptions\PrismRateLimitedException;
use Prism\Prism\Exceptions\PrismProviderOverloadedException;
use Prism\Prism\Exceptions\PrismServerException;
use Prism\Prism\Exceptions\PrismRequestTooLargeException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AiService
{
    /**
     * Turn any thrown error into a clear, user-facing message that distinguishes
     * "the AI is busy" (rate-limited / overloaded) from "we couldn't reach the AI"
     * (a network failure) from other failures. The raw error is still logged.
     */
    private function classifyError(\Throwable $e): string
    {
        // Genuinely the AI being busy — rate limited or the provider is overloaded.
        if ($e instanceof PrismRateLimitedException) {
            return 'The AI is busy (rate limited). Please wait a few seconds and try again.';
        }
        if ($e instanceof PrismProviderOverloadedException) {
            return 'The AI is overloaded right now. Please try again in a moment.';
        }
        if ($e instanceof PrismServerException) {
            return 'The AI service had a temporary error. Please try again shortly.';
        }
        if ($e instanceof PrismRequestTooLargeException) {
            return 'That request was too large for the AI. Try a shorter input.';
        }

        // Network failure — the request never reached the AI (no internet, DNS,
        // timeout, connection refused). cURL/Guzzle leak these strings even when
        // wrapped, so we sniff the message as well as the exception type.
        $msg = strtolower($e->getMessage());
        $networkSignals = [
            'curl error',            // any transport-level cURL failure (incl. 56 = SSL eof)
            'unexpected eof',
            'ssl',                   // TLS handshake / read errors
            'could not resolve host',
            'failed to connect',
            'connection refused',
            'connection timed out',
            'timed out',
            'name or service not known',
            'network is unreachable',
            'could not connect',
        ];
        if ($e instanceof ConnectionException || Str::contains($msg, $networkSignals)) {
            return 'Couldn’t reach the AI — check your internet connection and try again.';
        }

        // Anything else: genuinely unexpected.
        return 'The AI request failed unexpectedly. Please try again.';
    }

    /**
     * Send a prompt to Gemini and return the raw text — with a bounded per-attempt
     * timeout and automatic retries on TRANSIENT failures (provider overloaded,
     * 5xx, dropped/SSL connection). Those are by far the most common failures on
     * the free tier, so a couple of quiet retries turn most of them into a real
     * answer instead of a user-facing "AI is overloaded" error. Non-transient
     * errors (rate limit, bad request) throw immediately for classifyError().
     */
   private function runPrompt(string $prompt): string
{
    $models = array_merge(
        [config('services.ai.model', 'gemini-2.5-flash')],
        config('services.ai.fallbacks', [])
    );

    $lastError = null;

    foreach ($models as $model) {
        $maxAttempts = 3;

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            try {
                $response = Prism::text()
                    ->using(Provider::Gemini, $model)
                    ->withPrompt($prompt)
                    ->withClientOptions(['timeout' => 8, 'connect_timeout' => 5])
                    ->asText();

                return trim($response->text);
            } catch (\Throwable $e) {
                $lastError = $e;

                $isRateLimit = $e instanceof PrismRateLimitedException
                    || Str::contains(strtolower($e->getMessage()), ['rate', 'quota', 'exhausted']);

                // If THIS model is rate-limited, stop retrying it and move to the next model
                if ($isRateLimit) {
                    break;   // break the attempt loop → try next model
                }

                // Other transient errors: retry the same model
                if ($attempt < $maxAttempts && $this->isTransient($e)) {
                    usleep(500000 * $attempt);
                    continue;
                }

                // Non-transient, non-rate-limit: give up entirely
                throw $e;
            }
        }
        // fell out of the attempt loop due to rate limit → loop continues to next model
    }

    throw $lastError;
}

    /** Failures worth retrying — provider busy, server blip, or a dropped connection. */
    private function isTransient(\Throwable $e): bool
    {
        if (
            $e instanceof PrismProviderOverloadedException
            || $e instanceof PrismServerException
            || $e instanceof PrismRateLimitedException   // ← add this
            || $e instanceof ConnectionException
        ) {
            return true;
        }

        $msg = strtolower($e->getMessage());
        return Str::contains($msg, ['overloaded', 'curl error', 'unexpected eof', 'ssl', 'timed out', 'rate', 'quota', 'resource has been exhausted']);
    }

    /**
     * Ask the AI to suggest a squad from a pool of players.
     *
     * @param array $players  each: ['id','name','position','fantasy_price','team_name']
     * @param int   $squadSize    e.g. 11 or 5
     * @param int   $budget       e.g. 700 or 320
     * @param array $formation    e.g. ['DEF'=>4,'MID'=>3,'FWD'=>3]  (GK is always 1)
     * @param int   $maxPerTeam   e.g. 7 or 3
     * @return array{success:bool, player_ids:array, captain_id:?int, message:string}
     */
    public function suggestSquad(array $players, int $squadSize, int $budget, array $formation, int $maxPerTeam): array
    {
        // Build a compact player list for the prompt
        $playerLines = collect($players)->map(
            fn($p) =>
            "ID {$p['id']}: {$p['name']} ({$p['position']}, price {$p['fantasy_price']}, team {$p['team_name']})"
        )->implode("\n");

        $shapeText = "1 GK, {$formation['DEF']} DEF, {$formation['MID']} MID, {$formation['FWD']} FWD";

        $prompt = <<<PROMPT
        You are picking a fantasy football squad. Choose EXACTLY {$squadSize} players from this list:

        {$playerLines}

        RULES (must all be satisfied):
        - Exactly {$squadSize} players total.
        - Formation: {$shapeText}.
        - Total price must not exceed {$budget}.
        - No more than {$maxPerTeam} players from the same team.
        - Pick one captain from your chosen players (best expected scorer).

        Respond with ONLY a JSON object, no markdown, no explanation:
        {"player_ids": [list of chosen player ID numbers], "captain_id": chosen captain ID number}
        PROMPT;

        try {
            // Strip any markdown fences the model might add, then parse JSON
            $raw = $this->runPrompt($prompt);
            $raw = preg_replace('/^```(json)?|```$/m', '', $raw);
            $data = json_decode(trim($raw), true);

            if (!is_array($data) || !isset($data['player_ids'])) {
                return ['success' => false, 'player_ids' => [], 'captain_id' => null, 'message' => 'AI returned an unreadable response.'];
            }

            return [
                'success' => true,
                'player_ids' => array_map('intval', $data['player_ids']),
                'captain_id' => isset($data['captain_id']) ? (int) $data['captain_id'] : null,
                'message' => 'Suggestion ready.',
            ];

        } catch (\Throwable $e) {
            Log::error('AI suggestSquad failed', ['error' => $e->getMessage()]);
            return ['success' => false, 'player_ids' => [], 'captain_id' => null, 'message' => $this->classifyError($e)];
        }
    }

    /**
     * Parse a free-text match summary into structured result data.
     *
     * @param string $summary    the admin's casual description
     * @param array  $players    each: ['id','name','position','team_name']  (both teams in the fixture)
     * @param string $homeTeam   home team name
     * @param string $awayTeam   away team name
     * @return array{success:bool, home_score:?int, away_score:?int, events:array, warnings:array, message:string}
     */
    public function structureResult(string $summary, array $players, string $homeTeam, string $awayTeam): array
    {
        $roster = collect($players)->map(
            fn($p) =>
            "ID {$p['id']}: {$p['name']} ({$p['position']}, {$p['team_name']})"
        )->implode("\n");

        $prompt = <<<PROMPT
    You are converting a football match summary into structured data. Match: {$homeTeam} (home) vs {$awayTeam} (away).

    PLAYERS IN THIS MATCH (match names ONLY to these — never invent a player):
    {$roster}

    MATCH SUMMARY FROM ADMIN:
    "{$summary}"

    Extract:
    - home_score, away_score (integers)
    - events: each with player_id (from the list above), event_type (one of: goal, assist, yellow, red, own_goal, penalty_saved, penalty_miss, sub_on, sub_off), minute (integer or null), is_substitute (true/false)
    - warnings: a list of strings for anything you could NOT confidently match — e.g. a name matching two players, or a name not in the list. If unsure which player, DO NOT guess; add a warning instead and omit that event.
    - lineup: for any player whose MINUTES PLAYED or SAVES are mentioned, include {player_id, minutes (int 0-120, default 90 if not stated), saves (int, GK only, default 0)}. Only include players whose minutes differ from 90 OR who have saves mentioned. Don't list every player.
    - top_performers: an ordered list of the 3 BEST players in the match (best first), as player_ids from the list above. Judge from the summary — goals, assists, saves, defensive work, overall influence. A great performance without a goal still counts. Exactly 3 ids if the summary gives enough to judge; fewer only if very little detail.
    - status: the match status — "completed" if the match has finished/ended, "live" if in progress, "scheduled" if not started. Default "completed" if the summary describes a finished result.
    {"home_score": int, "away_score": int, "status": "completed", "events": [...], "lineup": [...], "top_performers": [...], "warnings": [...]}  

    PROMPT;

        try {
            $raw = $this->runPrompt($prompt);
            $raw = preg_replace('/^```(json)?|```$/m', '', $raw);
            $data = json_decode(trim($raw), true);

            if (!is_array($data)) {
                return ['success' => false, 'home_score' => null, 'away_score' => null, 'events' => [], 'warnings' => [], 'message' => 'AI returned an unreadable response.'];
            }

            // Build a set of valid player IDs for this fixture (so we never trust an invented ID)
            $validIds = collect($players)->pluck('id')->all();
            $validTypes = ['goal', 'assist', 'yellow', 'red', 'own_goal', 'penalty_saved', 'penalty_miss', 'sub_on', 'sub_off'];
            $events = collect($data['events'] ?? [])
                ->filter(fn($e) => in_array(($e['player_id'] ?? null), $validIds) && in_array(($e['event_type'] ?? null), $validTypes))
                ->map(fn($e) => [
                    'player_id' => (int) $e['player_id'],
                    'event_type' => $e['event_type'],
                    'minute' => isset($e['minute']) ? (int) $e['minute'] : null,
                    'is_substitute' => (bool) ($e['is_substitute'] ?? false),
                ])->values()->toArray();

            $validIds = collect($players)->pluck('id')->all();

            $lineup = collect($data['lineup'] ?? [])
                ->filter(fn($l) => in_array(($l['player_id'] ?? null), $validIds))
                ->map(fn($l) => [
                    'player_id' => (int) $l['player_id'],
                    'minutes' => isset($l['minutes']) ? (int) $l['minutes'] : 90,
                    'saves' => isset($l['saves']) ? (int) $l['saves'] : 0,
                ])->values()->toArray();
            $topPerformers = collect($data['top_performers'] ?? [])
                ->filter(fn($pid) => in_array($pid, $validIds))
                ->unique()
                ->take(3)
                ->map(fn($pid) => (int) $pid)
                ->values()
                ->toArray();
            $validStatuses = ['scheduled', 'live', 'completed', 'postponed'];
            $status = in_array($data['status'] ?? null, $validStatuses) ? $data['status'] : 'completed';
            return [
                'success' => true,
                'home_score' => isset($data['home_score']) ? (int) $data['home_score'] : null,
                'away_score' => isset($data['away_score']) ? (int) $data['away_score'] : null,
                'events' => $events,
                'warnings' => array_values((array) ($data['warnings'] ?? [])),
                'message' => 'Parsed.',
                'lineup' => $lineup,
                'status' => $status,
                'top_performers' => $topPerformers,
            ];

        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('AI structureResult failed', ['error' => $e->getMessage()]);
            return ['success' => false, 'home_score' => null, 'away_score' => null, 'events' => [], 'warnings' => [], 'lineup' => [], 'top_performers' => [], 'status' => 'completed', 'message' => $this->classifyError($e)];
        }
    }
    /**
     * Parse a free-text description into a tournament + its teams.
     *
     * @return array{success:bool, tournament:array, teams:array, warnings:array, message:string}
     */
    public function structureTournament(string $description): array
    {
        $today = now()->toDateString();
        $defaultSeason = date('Y') . '/' . (date('Y') + 1);

        $prompt = <<<PROMPT
    Convert this competition description into structured data. Today is {$today}.

    DESCRIPTION:
    "{$description}"

    Produce a tournament and its teams.

    TOURNAMENT fields:
    - name (string)
    - type: one of exactly "faculty_cup", "departmental_league", "friendly". Default "faculty_cup". Use "friendly" for casual/5-a-side cups, "departmental_league" if departments are mentioned.
    - season (string like "2025/2026"). Default "{$defaultSeason}" if not stated.
    - status: one of "upcoming", "active", "completed". Default "upcoming" unless they say it's running now (then "active").
    - squad_size: 5 or 11 only. Default 11. Use 5 if "5-a-side" / "5 aside" / "small" is mentioned.
    - start_date: YYYY-MM-DD or null. Interpret relative dates from today ({$today}). null if not stated.

    TEAMS: a list, each with:
    - name (string, required)
    - faculty (string or null)
    - department (string or null)
    - colour: a distinct hex colour per team (e.g. "#00E676", "#3B82F6", "#F59E0B", "#EF4444", "#A78BFA", "#F472B6"). Assign different colours.

    For anything unclear or assumed, add a short note to warnings.

    Respond with ONLY this JSON, no markdown:
    {"tournament": {"name": "", "type": "", "season": "", "status": "", "squad_size": 11, "start_date": null}, "teams": [{"name": "", "faculty": null, "department": null, "colour": ""}], "warnings": []}
    PROMPT;

        try {
            $raw = $this->runPrompt($prompt);
            $raw = preg_replace('/^```(json)?|```$/m', '', $raw);
            $data = json_decode(trim($raw), true);

            if (!is_array($data) || !isset($data['tournament'])) {
                return ['success' => false, 'tournament' => [], 'teams' => [], 'warnings' => [], 'message' => 'AI returned an unreadable response.'];
            }

            $t = $data['tournament'];

            // Validate/normalise tournament fields against your allowed values
            $validTypes = ['faculty_cup', 'departmental_league', 'friendly'];
            $validStatuses = ['upcoming', 'active', 'completed'];

            $tournament = [
                'name' => (string) ($t['name'] ?? ''),
                'type' => in_array($t['type'] ?? '', $validTypes) ? $t['type'] : 'faculty_cup',
                'season' => (string) ($t['season'] ?? $defaultSeason),
                'status' => in_array($t['status'] ?? '', $validStatuses) ? $t['status'] : 'upcoming',
                'squad_size' => in_array((int) ($t['squad_size'] ?? 11), [5, 11]) ? (int) $t['squad_size'] : 11,
                'start_date' => !empty($t['start_date']) ? $t['start_date'] : null,
            ];

            $teams = collect($data['teams'] ?? [])
                ->filter(fn($tm) => !empty($tm['name']))
                ->map(fn($tm) => [
                    'name' => (string) $tm['name'],
                    'faculty' => $tm['faculty'] ?? null,
                    'department' => $tm['department'] ?? null,
                    'colour' => preg_match('/^#[0-9A-Fa-f]{6}$/', $tm['colour'] ?? '') ? $tm['colour'] : '#00E676',
                ])->values()->toArray();

            return [
                'success' => true,
                'tournament' => $tournament,
                'teams' => $teams,
                'warnings' => array_values((array) ($data['warnings'] ?? [])),
                'message' => 'Parsed.',
            ];

        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('AI structureTournament failed', ['error' => $e->getMessage()]);
            return ['success' => false, 'tournament' => [], 'teams' => [], 'warnings' => [], 'message' => $this->classifyError($e)];
        }
    }
    /**
     * Generate a fixture schedule for a tournament's teams.
     *
     * @param array  $teams         each: ['id','name']  (the tournament's real teams)
     * @param string $instruction   e.g. "round robin, each plays once, 1 fixture per matchday"
     * @return array{success:bool, fixtures:array, warnings:array, message:string}
     */
    public function generateFixtures(array $teams, string $instruction): array
    {
        $teamList = collect($teams)->map(fn($t) => "ID {$t['id']}: {$t['name']}")->implode("\n");

        $prompt = <<<PROMPT
    Generate a football fixture schedule. Use ONLY these teams (never invent a team):
    {$teamList}

    INSTRUCTION:
    "{$instruction}"

    Rules:
    - Each fixture has home_team_id and away_team_id (both from the list above, and they must differ).
    - Assign a matchday (integer starting at 1) to each fixture.
    - For a "round robin" / "everyone plays everyone once": every team plays every other team exactly once. Spread fixtures across matchdays sensibly (a team shouldn't play twice on the same matchday).
    - If "home and away" / "double round robin": each pair plays twice (once home, once away).
    - Default to single round robin if unclear.

    For anything ambiguous or assumed, add a note to warnings.

    Respond with ONLY this JSON, no markdown:
    {"fixtures": [{"home_team_id": int, "away_team_id": int, "matchday": int}], "warnings": ["string"]}
    PROMPT;

        try {
            $raw = $this->runPrompt($prompt);
            $raw = preg_replace('/^```(json)?|```$/m', '', $raw);
            $data = json_decode(trim($raw), true);

            if (!is_array($data) || !isset($data['fixtures'])) {
                return ['success' => false, 'fixtures' => [], 'warnings' => [], 'message' => 'AI returned an unreadable response.'];
            }

            $validIds = collect($teams)->pluck('id')->all();

            // Keep only fixtures where BOTH teams are real and different
            $fixtures = collect($data['fixtures'] ?? [])
                ->filter(
                    fn($f) =>
                    in_array(($f['home_team_id'] ?? null), $validIds) &&
                    in_array(($f['away_team_id'] ?? null), $validIds) &&
                    ($f['home_team_id'] ?? null) !== ($f['away_team_id'] ?? null)
                )
                ->map(fn($f) => [
                    'home_team_id' => (int) $f['home_team_id'],
                    'away_team_id' => (int) $f['away_team_id'],
                    'matchday' => max(1, (int) ($f['matchday'] ?? 1)),
                ])->values()->toArray();

            return [
                'success' => true,
                'fixtures' => $fixtures,
                'warnings' => array_values((array) ($data['warnings'] ?? [])),
                'message' => 'Generated.',
            ];

        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('AI generateFixtures failed', ['error' => $e->getMessage()]);
            return ['success' => false, 'fixtures' => [], 'warnings' => [], 'message' => $this->classifyError($e)];
        }
    }

    /**
     * Generate a set of teams from a free-text description, for a tournament the
     * admin already picked. The admin reviews the proposals before they're saved.
     *
     * @param  string $instruction  e.g. "8 faculty teams" or a pasted list of faculties
     * @return array{success:bool, teams:array, warnings:array, message:string}
     */
    public function generateTeams(string $instruction): array
    {
        $prompt = <<<PROMPT
    Generate football teams for a Nigerian university campus tournament based on this description:
    "{$instruction}"

    Each team has:
    - name (string, required): a realistic team name (e.g. a faculty/department side).
    - faculty (string or null): the faculty if implied (e.g. "Engineering").
    - department (string or null): the department if implied (e.g. "Computer Science").
    - colour: a distinct hex colour per team (e.g. "#00E676", "#3B82F6", "#F59E0B", "#EF4444", "#A78BFA", "#F472B6"). Give EACH team a DIFFERENT colour.

    Rules:
    - If a count is given (e.g. "8 teams"), produce exactly that many teams.
    - If specific faculties/departments/names are listed, use them as the teams.
    - Keep names realistic; never invent nonsense entries or duplicate a team.

    For anything ambiguous or assumed, add a short note to warnings.

    Respond with ONLY this JSON, no markdown:
    {"teams": [{"name": "", "faculty": null, "department": null, "colour": ""}], "warnings": ["string"]}
    PROMPT;

        try {
            $raw = $this->runPrompt($prompt);
            $raw = preg_replace('/^```(json)?|```$/m', '', $raw);
            $data = json_decode(trim($raw), true);

            if (!is_array($data) || !isset($data['teams'])) {
                return ['success' => false, 'teams' => [], 'warnings' => [], 'message' => 'AI returned an unreadable response.'];
            }

            $teams = collect($data['teams'] ?? [])
                ->filter(fn($tm) => !empty($tm['name']))
                ->map(fn($tm) => [
                    'name' => (string) $tm['name'],
                    'faculty' => $tm['faculty'] ?? null,
                    'department' => $tm['department'] ?? null,
                    'colour' => preg_match('/^#[0-9A-Fa-f]{6}$/', $tm['colour'] ?? '') ? $tm['colour'] : '#00E676',
                ])->values()->toArray();

            return [
                'success' => true,
                'teams' => $teams,
                'warnings' => array_values((array) ($data['warnings'] ?? [])),
                'message' => 'Generated.',
            ];

        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('AI generateTeams failed', ['error' => $e->getMessage()]);
            return ['success' => false, 'teams' => [], 'warnings' => [], 'message' => $this->classifyError($e)];
        }
    }

    /**
     * Build a player roster for a team — either from a pasted list of names,
     * or generated to a requested position spread.
     *
     * @param string $instruction  e.g. "12 players: 2 GK, 4 DEF, 3 MID, 3 FWD" OR a pasted name list
     * @param int    $priceFloor   cheapest fantasy price (e.g. 40)
     * @param int    $priceCeil    priciest fantasy price (e.g. 90)
     * @return array{success:bool, players:array, warnings:array, message:string}
     */
    public function generateRoster(string $instruction, int $priceFloor = 40, int $priceCeil = 90): array
    {
        $prompt = <<<PROMPT
    Build a football team roster from this instruction:
    "{$instruction}"

    Rules:
    - If the instruction lists player NAMES, use those exact names and assign each a sensible position (GK, DEF, MID, FWD).
    - If it asks to GENERATE players with a position spread (e.g. "2 GK, 4 DEF, 3 MID, 3 FWD"), create realistic Nigerian-style player names to fill exactly that spread.
    - Each player needs: name, position (GK/DEF/MID/FWD), number (shirt number 1-99, unique within the team), fantasy_price.
    - fantasy_price between {$priceFloor} and {$priceCeil}, tiered by position: FWD and MID priciest, DEF mid, GK cheapest. Vary prices so they're not all identical.
    - A normal team has exactly 1-2 GK. Don't create more than 2 goalkeepers unless asked.

    For anything assumed or unclear, add a note to warnings.

    Respond with ONLY this JSON, no markdown:
    {"players": [{"name": "", "position": "", "number": int, "fantasy_price": int}], "warnings": []}
    PROMPT;

        try {
            $raw = $this->runPrompt($prompt);
            $raw = preg_replace('/^```(json)?|```$/m', '', $raw);
            $data = json_decode(trim($raw), true);

            if (!is_array($data) || !isset($data['players'])) {
                return ['success' => false, 'players' => [], 'warnings' => [], 'message' => 'AI returned an unreadable response.'];
            }

            $validPositions = ['GK', 'DEF', 'MID', 'FWD'];

            $players = collect($data['players'] ?? [])
                ->filter(fn($p) => !empty($p['name']) && in_array($p['position'] ?? '', $validPositions))
                ->map(fn($p) => [
                    'name' => (string) $p['name'],
                    'position' => $p['position'],
                    'number' => isset($p['number']) ? (int) $p['number'] : null,
                    'fantasy_price' => max($priceFloor, min($priceCeil, (int) ($p['fantasy_price'] ?? $priceFloor))),
                ])->values()->toArray();

            return [
                'success' => true,
                'players' => $players,
                'warnings' => array_values((array) ($data['warnings'] ?? [])),
                'message' => 'Roster ready.',
            ];

        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('AI generateRoster failed', ['error' => $e->getMessage()]);
            return ['success' => false, 'players' => [], 'warnings' => [], 'message' => $this->classifyError($e)];
        }
    }

    /**
     * Suggest a full set of predictions for one fixture.
     *
     * @param string $homeTeam
     * @param string $awayTeam
     * @param array  $homePlayers  each: ['id','name']
     * @param array  $awayPlayers  each: ['id','name']
     * @param int    $homeTeamId
     * @param int    $awayTeamId
     * @return array{success:bool, prediction:array, message:string}
     */
    public function suggestPrediction(string $homeTeam, string $awayTeam, array $homePlayers, array $awayPlayers, int $homeTeamId, int $awayTeamId): array
    {
        $homeList = collect($homePlayers)->map(fn($p) => "ID {$p['id']}: {$p['name']}")->implode("\n");
        $awayList = collect($awayPlayers)->map(fn($p) => "ID {$p['id']}: {$p['name']}")->implode("\n");

        $prompt = <<<PROMPT
    Suggest plausible predictions for this football fixture: {$homeTeam} (home) vs {$awayTeam} (away).

    {$homeTeam} players (team_id {$homeTeamId}):
    {$homeList}

    {$awayTeam} players (team_id {$awayTeamId}):
    {$awayList}

    Give ONE plausible suggestion for each:
    - result: "home", "draw", or "away"
    - home_score, away_score: small realistic integers (0-4) consistent with the result
    - scorer_id: a player id from the lists (likely first goalscorer)
    - clean_sheet_team: team_id ({$homeTeamId} or {$awayTeamId}) if you predict a clean sheet, else null
    - carded_id: a player id likely to be carded, or null

    Respond with ONLY this JSON, no markdown:
    {"result": "", "home_score": 0, "away_score": 0, "scorer_id": 0, "clean_sheet_team": null, "carded_id": null}
    PROMPT;

        try {
            $raw = $this->runPrompt($prompt);
            $raw = preg_replace('/^```(json)?|```$/m', '', $raw);
            $data = json_decode(trim($raw), true);

            if (!is_array($data)) {
                return ['success' => false, 'prediction' => [], 'message' => 'AI returned an unreadable response.'];
            }

            // Validate against real values
            $validPlayerIds = collect(array_merge($homePlayers, $awayPlayers))->pluck('id')->all();
            $validResults = ['home', 'draw', 'away'];

            $scorerId = in_array($data['scorer_id'] ?? null, $validPlayerIds) ? (int) $data['scorer_id'] : null;
            $cardedId = in_array($data['carded_id'] ?? null, $validPlayerIds) ? (int) $data['carded_id'] : null;
            $cleanSheet = in_array($data['clean_sheet_team'] ?? null, [$homeTeamId, $awayTeamId]) ? (int) $data['clean_sheet_team'] : null;

            return [
                'success' => true,
                'prediction' => [
                    'result' => in_array($data['result'] ?? null, $validResults) ? $data['result'] : null,
                    'home_score' => isset($data['home_score']) ? max(0, (int) $data['home_score']) : null,
                    'away_score' => isset($data['away_score']) ? max(0, (int) $data['away_score']) : null,
                    'scorer_id' => $scorerId,
                    'clean_sheet_team' => $cleanSheet,
                    'carded_id' => $cardedId,
                ],
                'message' => 'Suggested.',
            ];

        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('AI suggestPrediction failed', ['error' => $e->getMessage()]);
            return ['success' => false, 'prediction' => [], 'message' => $this->classifyError($e)];
        }
    }



}