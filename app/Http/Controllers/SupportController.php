<?php

namespace App\Http\Controllers;

use App\Http\Requests\SupportForm;
use Illuminate\Http\Request;

class SupportController extends Controller
{
    public function store(SupportForm $form)
    {
        return $form->save();
    }
}
