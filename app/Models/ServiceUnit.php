<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ServiceUnit extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'district', 'is_active'];

    // 1 หน่วยบริการ มีผู้ใช้งาน (ครู/หัวหน้า) ได้หลายคน
    public function users()
    {
        return $this->hasMany(User::class);
    }
}
