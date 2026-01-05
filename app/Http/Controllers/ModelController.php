<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateModelFirmwareRequest;
use App\Http\Requests\CreateRouterForm;
use App\Models\ModelFirmwares;
use App\Models\Models;
use App\Models\Router;
use App\Models\Distributor;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Symfony\Component\HttpFoundation\Response;

class ModelController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        
        if ($user->parent_id) {
            $parent = User::where('id', $user->parent_id)->first();
            $user = $parent;
        }
        $role = $user->roles;
        $title = Arr::pluck($role, 'title')[0];

        if ($title == 'Admin') {
            abort_if(Gate::denies('admin_model_access'), Response::HTTP_FORBIDDEN, '403 Forbidden');
        }
        // return Models::get();
        
        return Models::with(['router:model_id'])->get();
    }

    public function showApModel($id)
    {
        $routers = Router::where('owner_id', $id)->get();

        $models = Models::whereIn('id', $routers->pluck('model_id'))->get()->keyBy('id');

        /*$routersWithModels = $routers->map(function ($router) use ($models) {
            $router->model_info = $models->get($router->model_id);
            return $router;
        });*/

        return response()->json([
            'status' => 200,
            'message' => 'Success! Firmware File has been released for Model',
            'routers' => $models
        ]);
    }

/*    public function store(Request $request)
    {
        $user = Auth::user();
        if ($user->parent_id) {
            $parent = User::where('id', $user->parent_id)->first();
            $user = $parent;
        }
        $role = $user->roles;
        $title = Arr::pluck($role, 'title')[0];

        if ($title == 'Admin') {
            abort_if(Gate::denies('admin_model_create'), Response::HTTP_FORBIDDEN, '403 Forbidden');
        }

        $data = $request->all();

        $data['firmware_version'] = '0';
        return Models::create($data);
    }*/

    public function store(Request $request)
    {
        $user = Auth::user();
        if ($user->parent_id) {
            $parent = User::where('id', $user->parent_id)->first();
            $user = $parent;
        }
        $role = $user->roles;
        $title = Arr::pluck($role, 'title')[0];

        if ($title == 'Admin') {
            abort_if(Gate::denies('admin_model_create'), Response::HTTP_FORBIDDEN, '403 Forbidden');
        }

        $data = $request->all();

        $interfaces = [];
        foreach ($data as $key => $value) {
            if (preg_match("/^interface-(name|count|type|max-upload-speed|max-download-speed)-(\d+)$/", $key, $matches)) {
                $field = $matches[1];
                $index = $matches[2];

                if (!isset($interfaces[$index])) {
                    $interfaces[$index] = [];
                }

                switch ($field) {
                    case 'name':
                        $interfaces[$index]['interface-name'] = $value;
                        break;
                        case 'count':
                        $interfaces[$index]['interface-count'] = $value;
                        break;
                    case 'type':
                        $interfaces[$index]['interface-type'] = $value;
                        break;
                    case 'max-upload-speed':
                        $interfaces[$index]['max-upload-speed'] = $value;
                        break;
                    case 'max-download-speed':
                        $interfaces[$index]['max-download-speed'] = $value;
                        break;
                }

                unset($data[$key]);
            }
        }

        $interfaces = array_values($interfaces);

        $radio_bands = [];

        foreach ($data as $key => $value) {

            if (preg_match("/^([a-zA-Z]+-[a-zA-Z]+)-(\d+)$/", $key, $matches)) {
                if (!preg_match("/^interface-/", $key)) {

                    // Initialize the array if index does not exist
                    $index = $matches[2] - 1; // 0-based index
                    if (!isset($radio_bands[$index])) {
                        $radio_bands[$index] = [];
                    }

                    $property = $matches[1];
                    $radio_bands[$index][$property] = $value;
                }
            }
        }

        $radio_bands = array_values($radio_bands);

        $other_settings = [];
        foreach ($data as $key => $value) {
            if (!preg_match("/-\d+$/", $key)) {
                $other_settings[$key] = $value;
                unset($data[$key]);
            }
        }

        $settings = [
            'interfaces' => $interfaces,
            'radio_bands' => $radio_bands,
            'other_settings' => $other_settings
        ];

        return Models::create([
            'name' => $request->input('name'),
            'settings' => json_encode($settings),
            'firmware_version' => '0'
        ]);
    }

    public function firmwareStore(CreateModelFirmwareRequest $form)
    {
        return $form->save();
    }

    public function viewFile($id)
    {
        $user = Auth::user();
        if ($user->parent_id) {
            $parent = User::where('id', $user->parent_id)->first();
            $user = $parent;
        }
        $role = $user->roles;
        $title = Arr::pluck($role, 'title')[0];

        if ($title == 'Admin') {
            abort_if(Gate::denies('admin_model_firmware-upload'), Response::HTTP_FORBIDDEN, '403 Forbidden');
        }

        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'];
        $url = "{$protocol}://{$host}";

        /*$assignedfirmwares = ModelFirmwares::select('routers.id as router_id', 'model_firmwares.id', 'model_firmwares.model_id','model_firmwares.firmware_version', 'model_firmwares.created_at', 'model_firmwares.firmware_file', 'model_firmwares.released', DB::raw("CONCAT('$url/assets/uploads/firmware_files/', model_firmwares.firmware_file) as firmware_file_url"))
            ->join('routers', 'routers.model_id', '=', 'model_firmwares.model_id')
            ->where('model_firmwares.model_id', $id)->get();
        if (count($assignedfirmwares) > 0) {
            return $assignedfirmwares;
        } else {
            $firmwares = ModelFirmwares::select('id', 'model_id','firmware_version', 'created_at', 'firmware_file', 'released', DB::raw("CONCAT('$url/assets/uploads/firmware_files/', firmware_file, 'router_id') as firmware_file_url"), DB::raw("NULL as router_id"))->where('model_id', $id)->get();
            return $firmwares;
        }*/

        $firmwares = ModelFirmwares::select('id', 'model_id','firmware_version', 'created_at', 'firmware_file', 'released', 'file_name', DB::raw("CONCAT('$url/firmware/', firmware_file) as firmware_file_url"))->where('model_id', $id)->orderBy('created_at', 'desc')->get();
        return $firmwares;
    }

    public function releaseFirmware($id)
    {
        $firmwares = ModelFirmwares::where('id', $id)->first();

//        $modelFirmwares = ModelFirmwares::where('model_id', $firmwares->model_id);

        $updateModelFirmware = ModelFirmwares::where('model_id', $firmwares->model_id)->update(['released' => 0]);
        $releasedModelFirmware = ModelFirmwares::where('model_id', $firmwares->model_id)->where('id', $id)->update(['released' => 1]);

        $releasedFirmwareVersion = ModelFirmwares::where('model_id', $firmwares->model_id)->where('id', $id)->first();

        $model = Models::where('id', $firmwares->model_id)->update(['firmware_version' => $releasedFirmwareVersion->firmware_version, 'firmware_released_at' => Carbon::now()]);
        $modelDetails = Models::where('id', $firmwares->model_id)->first();

        if($model) {
            return response()->json(['status' => 200, 'message' => 'Success! Firmware File has been released for Model:' . $modelDetails->name]);
        } else {
            return response()->json(['status' => 400, 'message' => 'Failure! Firmware File cannot be released. Please try after some time!']);
        }
    }

    public function edit($id)
    {
        $model = Models::find($id);

        if (!$model) {
            return response()->json(['message' => 'Model not found'], 404);
        }

        return response()->json(['data' => $model], 200);


    }

    public function update(Request $request, $id)
    {
        $model = Models::find($id);

        if (!$model) {
            return response()->json(['message' => 'Model not found'], 404);
        }

        $user = Auth::user();
        if ($user->parent_id) {
            $parent = User::where('id', $user->parent_id)->first();
            $user = $parent;
        }
        $role = $user->roles;
        $title = Arr::pluck($role, 'title')[0];

        /*if ($title == 'Admin') {
            abort_if(Gate::denies('admin_model_update'), Response::HTTP_FORBIDDEN, '403 Forbidden');
        }*/

        // Retrieve all request data
        $data = $request->all();

        // Parse interfaces
        $interfaces = [];
        foreach ($data as $key => $value) {
            if (preg_match("/^interface-(name|count|type|max-upload-speed|max-download-speed)-(\d+)$/", $key, $matches)) {
                $field = $matches[1];
                $index = $matches[2];

                if (!isset($interfaces[$index])) {
                    $interfaces[$index] = [];
                }

                switch ($field) {
                    case 'name':
                        $interfaces[$index]['interface-name'] = $value;
                        break;
                        case 'count':
                        $interfaces[$index]['interface-count'] = $value;
                        break;
                    case 'type':
                        $interfaces[$index]['interface-type'] = $value;
                        break;
                    case 'max-upload-speed':
                        $interfaces[$index]['max-upload-speed'] = $value;
                        break;
                    case 'max-download-speed':
                        $interfaces[$index]['max-download-speed'] = $value;
                        break;
                }

                unset($data[$key]);
            }
        }
        $interfaces = array_values($interfaces);

        // Parse radio bands
        $radio_bands = [];
        foreach ($data as $key => $value) {
            if (preg_match("/^([a-zA-Z]+-[a-zA-Z]+)-(\d+)$/", $key, $matches)) {
                if (!preg_match("/^interface-/", $key)) {
                    $index = $matches[2] - 1; // 0-based index
                    if (!isset($radio_bands[$index])) {
                        $radio_bands[$index] = [];
                    }
                    $property = $matches[1];
                    $radio_bands[$index][$property] = $value;
                }
            }
        }
        $radio_bands = array_values($radio_bands);

        // Parse other settings
        $other_settings = [];
        foreach ($data as $key => $value) {
            if (!preg_match("/-\d+$/", $key)) {
                $other_settings[$key] = $value;
                unset($data[$key]);
            }
        }

        // Update settings structure
        $settings = [
            'interfaces' => $interfaces,
            'radio_bands' => $radio_bands,
            'other_settings' => $other_settings
        ];

        // Update the model's attributes and save
        $model->name = $request->input('name', $model->name);
        $model->settings = json_encode($settings);

        $model->save();

        return response()->json(['data' => $model, 'message' => 'Model updated successfully'], 200);
    }

    function destroy($id){
        try{
            $deviceModel = Models::findOrFail($id);

            // Check if inventory is assigned to this model_id
            $inventoryExists = Router::where('model_id', $deviceModel->id)->exists();

            if ($inventoryExists) {
                return response()->json([
                    'message' => 'Inventory has been assigned to this model, cannot delete.'
                ], Response::HTTP_NON_AUTHORITATIVE_INFORMATION); //203
            }

            $deviceModel->delete();

            return response()->json([
                'message' => 'Model deleted successfully.',
            ],200);
        }
        catch(\Exception $e){
            Log::error('Issue on Deleting Model', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Something Went Deleting Model', 'error' => $e->getMessage()], 500);
        }

    }

    public function suspend($id)
    {
        try{
            $model = Models::findOrFail($id);
            
            if($model->suspend == 'suspend'){
                return response()->json([
                    'message' => 'Model is already suspended.'
                ], Response::HTTP_NON_AUTHORITATIVE_INFORMATION); //203
            }

            $model->suspend = 'suspend';
            $model->save();
            return response()->json(['message' => 'Model suspended successfully'], 200);
        }
        catch(\Exception $e){
            Log::error('Issue on Suspending Model', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Something Went Suspending Model', 'error' => $e->getMessage()], 500);
        }
    }

    public function unsuspend($id)
    {
        try{
            $model = Models::findOrFail($id);
            
            if($model->suspend == ''){
                return response()->json([
                    'message' => 'Model is already unsuspended.'
                ], Response::HTTP_NON_AUTHORITATIVE_INFORMATION); //203
            }

            $model->suspend = '';
            $model->save();
            return response()->json(['message' => 'Model unsuspended successfully'], 200);
        }
        catch(\Exception $e){
            Log::error('Issue on Suspending Model', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Something Went Unsuspending Model', 'error' => $e->getMessage()], 500);
        }
    }

    public function getModelfromId($id){

        $selectedData = Router::with("model:id,name")->where('id',$id)->first();
        if (!$selectedData->model) {
            return response()->json(['message' => 'Model not found'], 404);
        }

        return response()->json(['data' => $selectedData->model], 200);


    }




}
