<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use PHPOpenSourceSaver\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements JWTSubject
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $guarded = [];

    protected $appends = ['photo_url', 'full_name'];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    protected $dates = ['otp_verified_on'];

    public function roles()
    {
        return $this->belongsToMany(Role::class);
    }

    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims()
    {
        return [];
    }

    public function isOfType($type)
    {
        return $this->role == config('constants.roles.' . $type);
    }

    public function isLocationOwner()
    {
        return $this->isOfType('location_owner');
    }

    public function isAdmin()
    {
        return $this->isOfType('admin');
    }

    public function isDistributor()
    {
        return $this->isOfType('distributor');
    }

    public function isPDO()
    {
        return $this->isOfType('location_owner');
    }

    public function scopeLocationOwners($query)
    {
        return $query->where('role', config('constants.roles.location_owner'));
    }

    public function scopeCustomers($query)
    {
        return $query->where('role', config('constants.roles.customer'));
    }

    public function scopeAdmins($query)
    {
        return $query->where('role', config('constants.roles.admin'));
    }

    public function profile()
    {
        return $this->hasOne(Profile::class);
    }

    public function pdoaPlan()
    {
        return $this->belongsTo(PdoaPlan::class, 'pdo_type');
    }

     public function router()
    {
        return $this->hasMany(Router::class, 'owner_id','id');
    }

    public function getPhotoUrlAttribute()
    {
        if ($this->photo) {
            return route('api.media', $this->photo);
        }
        return null;
    }

    public function getFullNameAttribute()
    {
        return trim($this->first_name . ' ' . $this->last_name);
    }

    private static function hideFirstNCharacters($string, $n = 4)
    {
        $mask = '';
        foreach (range(1, $n) as $i) {
            $mask .= '*';
        }
        return $mask . substr($string, 4);
    }

    public static function getOrCreateByDetails($details)
    {
        $phoneUser = User::where('phone', $details['phone'])->first();
        $emailUser = User::where('email', $details['email'])->first();

        if (($phoneUser) && ($emailUser)) {

            if ($phoneUser->id == $emailUser->id) {
                return $phoneUser;
            }
        }
        #return $phoneUser;
        if ($phoneUser && $emailUser && $phoneUser->id != $emailUser->id) {
            $message = $details['phone'] . ' is connected to ' . self::hideFirstNCharacters($phoneUser->email);
            $message .= ' & ' . $details['email'] . ' is connected to ' . self::hideFirstNCharacters($emailUser->phone);
            abort(422, 'Sorry! ' . $message);
        }

        $user = null;
        if ($phoneUser && !$emailUser) {
            if ($phoneUser->email != $details['email']) {
                $message = $details['phone'] . ' is connected to ' . self::hideFirstNCharacters($phoneUser->email);
                abort(422, 'Sorry! ' . $message);
            }
            $user = $phoneUser;
        }

        if (!$phoneUser && $emailUser) {
            if ($emailUser->phone != $details['phone']) {
                $message = $details['email'] . ' is connected to ' . self::hideFirstNCharacters($emailUser->phone);
                abort(422, 'Sorry! ' . $message);
            }
            $user = $emailUser;
        }

        if (!$user) {
            $user = new User();
        }
        $user->first_name = $details['name'];
        $user->email = $details['email'];
        $user->phone = $details['phone'];
        if (!isset($user->role)) {
            $user->role = config('constants.roles.customer');
        }
        $user->save();
        return $user;
    }
    public function pdoSettings()
    {
        return $this->hasOne(PdoSettings::class, 'pdo_id');
    }

    public function pdoSmsQuota()
    {
        return $this->hasMany(PdoSmsQuota::class, 'pdo_id')->latest();
    }

    public function pdoCredits()
    {
        return $this->hasMany(PdoCredits::class, 'pdo_id');
    }
}
