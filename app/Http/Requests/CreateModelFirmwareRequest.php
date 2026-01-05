<?php

namespace App\Http\Requests;

use App\Models\ModelFirmwares;
use Illuminate\Support\Facades\DB;
use Illuminate\Foundation\Http\FormRequest;

class CreateModelFirmwareRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */

    protected $user;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        $rules = [
            'id' => 'nullable|numeric',
            'firmware_version' => 'required|max:150|unique:model_firmwares,firmware_version,NULL,id,model_id,' . $this->id,
            'firmware_file' => 'required|max:52428800',
            'released' => 'nullable'
        ];

        return $rules;
    }

    public function save()
    {
        try {

            DB::beginTransaction();

            // check folder exists
            $folder = $_SERVER['DOCUMENT_ROOT'].'/firmware/updates/';
            if (!file_exists($folder)) {
                mkdir($folder, 0777, true);
            }
            $originalFilename = $this->firmware_file->getClientOriginalName();

            $md5Hash = md5(file_get_contents($this->firmware_file->getRealPath()));
            $newFilename = $md5Hash . $originalFilename;

            $modelFirmware = new ModelFirmwares();
            $modelFirmware->fill($this->only( 'firmware_version', 'released'));
            $modelFirmware->model_id = $this->id;
            $modelFirmware->firmware_file = ($this->firmware_file) ? $newFilename : '';
            $modelFirmware->file_name = ($this->firmware_file) ? $originalFilename : '';
            $modelFirmware->save();

            // file upload using firmware id
            $firmwareFileFolder = $folder;

                if($modelFirmware->id) {
                    $firmwareFile = $this->firmware_file;
                    $firmwareFileName = $originalFilename;
                    $firmwareFile->move($folder, $firmwareFileName);
                }
                DB::commit();
                return $modelFirmware;

            }
        catch(Exception $e) {
            return [];
        }
    }
}
