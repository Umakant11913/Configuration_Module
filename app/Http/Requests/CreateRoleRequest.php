<?php

namespace App\Http\Requests;

class CreateRoleRequest extends BaseRequest
{
    public function rules()
    {
        return [
            'title' => 'required|string',
        ];
    }

}
