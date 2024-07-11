<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FreelancerRating extends Model
{
    use HasFactory;
    protected $guarded = [];


    public function freelancer()
    {
        return $this->belongsTo(Freelancer::class);
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }
}
