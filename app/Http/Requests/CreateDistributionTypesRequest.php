<?php

namespace App\Http\Requests;

use App\Models\DistributorTypes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Foundation\Http\FormRequest;

class CreateDistributionTypesRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    // public function authorize()
    // {
    //     return false;
    // }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        return [
            //
        ];
    }

    public function save()
    {
        DB::beginTransaction();

        $distributor_type = new DistributorTypes();
        $distributor_type->fill($this->only('name', 'status'));
        $distributor_type->save();
        
        DB::commit();

        return $distributor_type;

    }
}
