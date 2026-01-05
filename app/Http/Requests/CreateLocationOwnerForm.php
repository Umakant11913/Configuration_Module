<?php

namespace App\Http\Requests;

use App\Events\PdoAssignEvent;
use App\Models\Location;
use App\Models\Pdo_Sms_Quota;
use App\Models\PdoaPlan;
use App\Models\PdoCredits;
use App\Models\PdoCreditsHistory;
use App\Models\PdoSettings;
use App\Models\PdoSmsQuota;
use App\Models\Profile;
use App\Models\Router;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;

class CreateLocationOwnerForm extends BaseRequest
{
    protected $user;

    // public function authorize()
    // {
    //     $user = Auth::user();
    //     return $user->isAdmin();
    // }

    public function rules()
    {
        return [
            'id' => 'nullable|numeric',
            'first_name' => 'required|max:150',
            'last_name' => 'required|max:150',
            'email' => 'required|email',
            'password' => 'required_without:id|max:150',
            'company_name' => 'nullable|max:150',
            'gst_no' => 'nullable|max:150',
            'address' => 'nullable|max:255',
            'city' => 'nullable|max:255',
            'postal_code' => 'nullable|max:10',
            'pdo_type' => 'nullable|integer',
        ];
    }

    protected function setup()
    {
        $this->user = new User();
        $this->user->role = config('constants.roles.location_owner');
        if ($this->id) {
            $this->user = User::findOrFail($this->id);
        }
    }

    public function save()
    {
        try {

            $checkEmail = User::where('email', $this->email)->get();

            if ((!$this->id) && (empty($this->postal_code))) {
                return response()->json(['message' => 'Please select at least one locations!!'], 404); // Status code here
            }
            if ((count($checkEmail) > 0) && (!$this->id)) {

                return response()->json(['message' => 'Email id already exist!!'], 404); // Status code here

            } else {
                $user = Auth::user();
                if ($user->parent_id) {
                    $parent = User::where('id', $user->parent_id)->first();
                    $user = $parent;
                }
                $loginUserRole = $user->role;
                DB::beginTransaction();

                $this->user->fill($this->only('first_name', 'last_name', 'email', 'pdo_type', 'contract_expiry_date','thresholdLimit'));

                $pdo_type = PdoaPlan::where('id', $this->pdo_type)->first();

                if($pdo_type->validity_period !== null && $pdo_type->grace_period !== null && $pdo_type->sms_quota !== null && $this->credits !== null) {
                    $this->user->auto_renew_subscription = 1;
                } else {
                    $this->user->auto_renew_subscription = 0;
                }

                if ($loginUserRole == config('constants.roles.admin')) {
                    $this->user->status = 1; // approved
                } else {
                    $this->user->status = 0; // pending
                }
                if ($this->password) {
                    $this->user->password = bcrypt($this->password);
                }
                $this->user->is_parentId = $user->id;
                $this->user->save();
                $this->user->roles()->sync('2');

                $profile = $this->user->profile;
                if (!$profile) {
                    $profile = new Profile();
                    $profile->user_id = $this->user->id;
                }

                $profile->fill($this->only('company_name', 'gst_no', 'address', 'city', 'postal_code'));
                $profile->save();

                $credits = PdoCredits::where('pdo_id', $this->user->id)->first();
                $pdo_plan = PdoaPlan::where('id', $this->user->pdo_type)->first();
                $pdo_sms_quota = PdoSmsQuota::where('id', $this->user->id)->first();
                if (!$credits) {
                    if($this->credits > 0 && $this->credits !== null) {
                        $credits = new PdoCredits();
                        $credits->pdo_id = $this->user->id;
                        $credits->credits = $this->credits;
                        $credits->type = 'add-on';
                        $credits->save();

                        $pdoCreditHistory = new PdoCreditsHistory();
                        $pdoCreditHistory->pdo_id = $this->user->id;
                        $pdoCreditHistory->credits = $this->credits;
                        $pdoCreditHistory->save();
                    }
                    if ($pdo_plan->validity_period !== null) {
                        $expiry_date = $credits->created_at->addMonths($pdo_plan->validity_period);
                        $credits->expiry_date = $expiry_date;
                        $credits->save();
                    }
                } else {
                    $credits->credits = $this->credits;
                    $credits->save();
                }

                if($this->default_sms > 0 && $this->default_sms !== null) {
                    $pdo_sms_quota = new PdoSmsQuota();
                    $pdo_sms_quota->pdo_id = $this->user->id;
                    $pdo_sms_quota->sms_quota = $this->default_sms;
                    $pdo_sms_quota->type = 'default';
                    $pdo_sms_quota->save();
                }

                $pdoPlan = PdoaPlan::where('id', $this->user->pdo_type)->first();
                if ($pdoPlan !=null && $credits !=null) {
                    event(new PdoAssignEvent($this->user, $pdoPlan , $credits));
                }
                if ($this->user->wasRecentlyCreated) {
                    $this->sendResetMail($this->user);
                }

                DB::commit();

                return $this->user;

            }

        } catch (Exception $e) {

            return [];

        }
    }

    private function sendResetMail($user)
    {
        $token = Password::getRepository()->create($user);
        $user->sendPasswordResetNotification($token);
    }

    private function smsQuota($id)
    {
        $pdoSettings = PdoSettings::where('pdo_id', $id)->first();
        if (!$pdoSettings) {
            $pdoSettings = new PdoSettings();
            $pdoSettings->pdo_id = $id;
        }

        $pdoSettings->period_quota = $this->period_quota;
        $pdoSettings->period_type = $this->period_type;

        $pdoSettings->fill($this->only('add_on_available'));
        $pdoSettings->save();

        switch ($pdoSettings->period_type) {
            case 'monthly':
                $end_date = ($pdoSettings->created_at)->addDays(29);
                break;
            case 'quarterly':

                $end_date = ($pdoSettings->created_at)->addMonths(3)->subDay();
                break;
            case 'half-yearly':

                $end_date = ($pdoSettings->created_at)->addMonths(6)->subDay();
                break;
            case 'annually':
                $end_date = ($pdoSettings->created_at)->addYear()->subDay();
                break;
            default:
                $end_date = ($pdoSettings->created_at);
                break;
        }

        $created_at_formatted = ($pdoSettings->created_at)->format('Y-m-d');
        $end_date_formatted = $end_date->format('Y-m-d');


        $pdoSmsQuota = PdoSmsQuota::where('pdo_id', $id)->first();
        if (!$pdoSmsQuota) {
            $pdoSmsQuota = new PdoSmsQuota();
            $pdoSmsQuota->pdo_id = $id;
            $pdoSmsQuota->start_date = $created_at_formatted;
            $pdoSmsQuota->end_date = $end_date_formatted;
            $pdoSmsQuota->sms_quota = $pdoSettings->period_quota;
            $pdoSmsQuota->sms_used = '0';
            $pdoSmsQuota->period_type = $pdoSettings->period_type;
            $pdoSmsQuota->save();
        } else {
            $pdoSmsQuota->start_date = $created_at_formatted;
            $pdoSmsQuota->end_date = $end_date_formatted;
            $pdoSmsQuota->sms_quota = $pdoSettings->period_quota;
            $pdoSmsQuota->period_type = $pdoSettings->period_type;
            $pdoSmsQuota->save();
        }

    }

   private function addCredits($user ,$request_credits)
    {
            $credits = PdoCredits::where('pdo_id', $user->id);
            if (!$credits) {
                $credits = new PdoCredits();
                $credits->pdo_id = $user->id;
                $credits->credits = $request_credits;
                $credits->save();
            } else {
                $credits->credits = $request_credits;
            }

    }


}
