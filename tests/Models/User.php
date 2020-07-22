<?php

namespace Tests\Models;

use Illuminate\Auth\Authenticatable;
use Illuminate\Auth\Passwords\CanResetPassword;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Contracts\Auth\CanResetPassword as CanResetPasswordContract;
use Illuminate\Notifications\Notifiable;
use OfflineAgency\MongoAutoSync\Http\Models\MDModel;

/**
 * Class User.
 * @property string $_id
 * @property string $name
 * @property string $title
 * @property int $age
 * @property \Carbon\Carbon $birthday
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class User extends MDModel implements AuthenticatableContract, CanResetPasswordContract
{
    use Authenticatable, CanResetPassword, Notifiable;

    protected $connection = 'mongodb';
    protected $dates = ['birthday', 'entry.date'];
    protected static $unguarded = true;

    public function addresses()
    {
        return $this->embedsMany(Address::class);
    }

    public function father()
    {
        return $this->embedsOne(self::class);
    }

    public function getDateFormat()
    {
        return 'l jS \of F Y h:i:s A';
    }
}
