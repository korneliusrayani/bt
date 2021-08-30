<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product
{
    public $id;
    public $slug;
    public $title;
    public $price;
    public $description;
    public $type;
}
