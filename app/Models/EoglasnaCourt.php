<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EoglasnaCourt extends Model
{
    protected $table = 'eoglasna_courts';
    protected $fillable = ['code','name','court_type'];
}
