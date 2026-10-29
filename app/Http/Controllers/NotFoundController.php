<?php

namespace App\Http\Controllers;

class NotFoundController extends Controller
{
    public function __invoke(): never
    {
        abort(404);
    }
}
