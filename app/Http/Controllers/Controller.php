<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\BuildsLikePatterns;
use App\Http\Controllers\Concerns\ResolvesPerPage;
use App\Http\Controllers\Concerns\SortsListings;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

abstract class Controller
{
    use AuthorizesRequests, BuildsLikePatterns, ResolvesPerPage, SortsListings;
}
