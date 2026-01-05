<?php
namespace App\Http\Controllers;

    use App\Models\Settings;
    use Illuminate\Http\JsonResponse;
    use Illuminate\Http\Request;
    use Illuminate\Http\Response;
    use Illuminate\Support\Facades\Log;

class SettingsController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api', [
            'except' => ['index']
        ]);
    }


    public function index()
    {
        $settings = Settings::firstOrFail();
        $settingsValue = json_decode($settings->settings);

        return $settingsValue;
    }

    public function store(Request $request)
    {
        $showRegister = $request->get('status');

        $settings = [
            'showRegister' => $showRegister
        ];

        try {
            Settings::updateOrCreate(['id' => 1], [
                'settings' => json_encode($settings)
            ]);

            return new JsonResponse([
                'success' => true,
                'message' => 'Settings updated successfully',
            ], Response::HTTP_CREATED);
        } catch (\Throwable $exception) {
            Log::error('Failed to update settings', [
                'message' => $exception->getMessage(),
            ]);

            return new JsonResponse([
                'success' => false,
                'message' => 'Failed to update settings',
                'errors' => [
                    $exception->getMessage(),
                ],
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

    }
}
