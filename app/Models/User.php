<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['name', 'email', 'password', 'faculty', 'tokens', 'is_admin', 'phone','referral_code', 'referred_by',])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Nigerian mobile number format accepted across the app (registration,
     * profile, admin). Matches what AirtimeService::detectNetwork() can read:
     * 0XXXXXXXXXX, +234XXXXXXXXXX, or 234XXXXXXXXXX (next digit 7/8/9).
     * Payout-critical — keep this the single source of truth.
     */
    const PHONE_REGEX = '/^(?:\+?234|0)[789]\d{9}$/';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
        ];
    }

    // ─── Relationships ─────────────────────────────────────────────────────────

    public function tokenWallet(): HasOne
    {
        return $this->hasOne(TokenWallet::class);
    }

    public function tokenTransactions(): HasMany
    {
        return $this->hasMany(TokenTransaction::class);
    }

    public function fantasyTeams(): HasMany
    {
        return $this->hasMany(FantasyTeam::class);
    }

    public function predictions(): HasMany
    {
        return $this->hasMany(Prediction::class);
    }

    public function tournamentPredictions(): HasMany
    {
        return $this->hasMany(TournamentPrediction::class);
    }

    public function miniLeagues(): BelongsToMany
    {
        return $this->belongsToMany(MiniLeague::class, 'mini_league_user')->withTimestamps();
    }
    public function airtimePayouts(): HasMany
    {
        return $this->hasMany(AirtimePayout::class);
    }

    /** A unique, human-friendly referral code. */
public static function generateReferralCode(): string
{
    do {
        // e.g. "PIQ7K2QX" — readable, avoids ambiguous chars
        $code = 'PIQ' . strtoupper(\Illuminate\Support\Str::random(5));
    } while (self::where('referral_code', $code)->exists());

    return $code;
}

/** Who this user referred. */
public function referredUsers()
{
    return $this->hasMany(User::class, 'referred_by');
}

/** Who referred this user. */
public function referrer()
{
    return $this->belongsTo(User::class, 'referred_by');
}
}
