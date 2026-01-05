<?php

namespace App\Http\Controllers;

use App\Models\DistributorTypes;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Http\Requests\CreateDistributionTypesRequest;

class DistributorTypesController extends Controller
{
    public function index()
    {

        $user = Auth::user();
        $distributorTypes = DistributorTypes::orderBy('id', 'DESC')->get();
        // return $distributorTypes;

    }

    public function store(CreateDistributionTypesRequest $form)
    {
        return $form->save();
    }
}
