<?php

namespace App\Http\Controllers;

use App\Events\PdoAddCreditsEvents;
use App\Events\PdoAddSmsEvents;
use App\Http\Requests\CreateLocationOwnerForm;
use App\Models\CreditsHistoy;
use App\Models\Location;
use App\Models\PdoAddOnSmsHistory;
use App\Models\PdoaPlan;
use App\Models\PdoCredits;
use App\Models\PdoCreditsHistory;
use App\Models\PdoSettings;
use App\Models\PdoSmsQuota;
use App\Models\Router;
use App\Models\SmsHistory;
use App\Models\User;
use App\Models\PinCode;
use App\Models\PayoutLog;
use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Models\Distributor;
use App\Models\Profile;
use App\Models\AssignPdoRequest;
use App\Models\DistributorLocations;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use DB;
use Symfony\Component\HttpFoundation\Response;


class LocationOwnerController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        if ($user->parent_id) {
            $parent = User::where('id', $user->parent_id)->first();
            $user = $parent;
        }
        // if (!$user->isAdmin()) {
        //     return [];
        // }
        if ($user->isAdmin()) {
            return User::locationOwners()->with('profile', 'pdoaPlan')->where('parent_id', null)->get();
        } else {
            return User::locationOwners()->where('is_parentId', $user->id)->with('profile', 'pdoaPlan')->where('parent_id', null)->get();
        }
    }

    public function index_with_payouts()
    {

        $data = array();
        $user = Auth::user();
        if ($user->parent_id) {
            $parent = User::where('id', $user->parent_id)->first();
            $user = $parent;
        }
        $role = $user->roles;
        $title = Arr::pluck($role, 'title')[0];

        if ($title == 'Admin') {
            abort_if(Gate::denies('admin_accounts_access'), Response::HTTP_FORBIDDEN, '403 Forbidden');
        }

        if ($title == 'Distributor') {
            abort_if(Gate::denies('distributor_accounts_access'), Response::HTTP_FORBIDDEN, '403 Forbidden');
        }

        if ($user->isAdmin()) {
            $data['owners'] = User::with('router', 'profile', 'pdoaPlan', 'pdoSmsQuota', 'pdoCredits')
            ->locationOwners()
            ->where('parent_id', null)
            ->get();
        } else {
            $data['owners'] = User::locationOwners()->where('is_parentId', $user->id)->with('profile', 'pdoaPlan')->where('parent_id', null)->get();
        }


        $data['payouts'] = PayoutLog::groupBy('pdo_owner_id')
            ->selectRaw('sum(payout_amount) as payout_amount, pdo_owner_id')
            ->get();
        $owner_with_payout = array();
        $i = 0;

        foreach ($data['owners'] as $key) {

            $key->parent_id = isset($user->id) ? $user->id : null;
            $parentId = $key->is_parentId;

            $getdata = User::find($parentId);

            if ($getdata) {
                $key->owner_name = ($getdata->id === $user->id) ? 'Self' : $getdata->first_name . ' ' . $getdata->last_name;
            } else {
                $key->owner_name = 'NA';
            }
            $owner_id = $key->id;

            $key->locationsCnt = Location::where('owner_id', $owner_id)->count();

            $amount = 0;
            foreach ($data['payouts'] as $payout) {
                if ($payout->pdo_owner_id == $owner_id) {
                    $amount = $payout->payout_amount;
                }
                $key->payout_amount = $amount;
                $data['owner_with_payout'][$i] = $key;
            }
            $i++;
        }

        return $data;
        // return compact('owners','payouts', 'owner_with_payout');
    }

    public function store(CreateLocationOwnerForm $form)
    {
        $user = Auth::user();
        if ($user->parent_id) {
            $parent = User::where('id', $user->parent_id)->first();
            $user = $parent;
        }
        $role = $user->roles;
        $title = Arr::pluck($role, 'title')[0];

        if ($title == 'Admin') {
            abort_if(Gate::denies('admin_accounts_create'), Response::HTTP_FORBIDDEN, '403 Forbidden');
        }

        if ($title == 'Distributor') {
            abort_if(Gate::denies('distributor_accounts_create'), Response::HTTP_FORBIDDEN, '403 Forbidden');
        }

        return $form->save();
    }

    public function statusUpdate(Request $request)
    {
        $updateStatus = User::where('id', $request->owner_id)->update([
            'status' => $request->status
        ]);

        return $updateStatus;
    }

    public function show(User $user)
    {
        $user->load('profile', 'pdoaPlan', 'pdoSettings','pdoCredits', 'PdoSmsQuota');
        $pincodes = PinCode::where('id', $user['profile']->postal_code)->orWhere('pin_code', $user['profile']->postal_code)->first(['id', 'pin_code as text']);
        $user['profile']['pincode'] = ($pincodes) ? $pincodes : [];
        return $user;
    }

    public function login_as_pdo_owner($id)
    {
        // return $id;
        $user = Auth::user();
        // if (!$user->isAdmin() && !$user->isDistributor()) {
        //     return [];
        // }
        $owner_id = $id;
        $pdo_owner = User::where('id', $id)->first();
        if ($pdo_owner->parent_id) {
            $parent = User::where('id', $pdo_owner->parent_id)->first();
            $pdo_owner = $parent;
        }
        $token = Auth::login($pdo_owner);

        $pdo_owner->login_at = $pdo_owner->last_login_at;
        $pdo_owner->last_login_at = Carbon::now();
        $pdo_owner->save();

        //$user = Auth::user();
        //$accessToken = $user->createToken('token')->accessToken;

        /*
        if (!$customer && $user->role == config('constants.roles.customer')) {
            abort(403, 'Sorry! You are not allowed to login.');
        }
        */
        return compact('token', 'pdo_owner', 'owner_id');
    }

    public function onloadPdo()
    {
        $user = Auth::user();
        if ($user->parent_id) {
            $parent = User::where('id', $user->parent_id)->first();
            $user = $parent;
        }
        if ($user->role == 0) {
            $data = ['data' => User::where('role', 3)->get(), 'logged_in_user' => $user];
            return $data;
        } elseif ($user->role == 3 && $user->distributor_type == 1) {
            $data = ['data' => User::where('role', 3)->where('is_parentId', $user->id)->get(), 'logged_in_user' => $user];
            return $data;
        } else {
            return [];
        }
    }

    public function assignPdo(Request $request, User $user)
    {

        $user = Auth::user();
        if ($user->parent_id) {
            $parent = User::where('id', $user->parent_id)->first();
            $user = $parent;
        }
        $assign_status = ($user->isAdmin()) ? 1 : 0;

        $to = $request->to_owner;
        $from = $request->id;

        $from_PDO_planId = User::find($from)->pdo_type;

        $to_dist_plan_Id = Distributor::with('owner', 'distributorPlan')->where('owner_id', $to)->first();

        if ($assign_status && $user->id == $to) {
            $updatePdoOwner = User::where('id', $from)->update([
                'is_parentId' => $to,
            ]);

            $assignRequest = AssignPdoRequest::where('from_id', $from)->first();

            if ($assignRequest) {
                $assignRequest->update([
                    'from_id' => $from,
                    'to_id' => $to,
                    'status' => $assign_status,
                    'user_id' => $to
                ]);
            }
            return $this->index_with_payouts();
        }

        if ($from_PDO_planId != null && $to_dist_plan_Id != null && isset($to_dist_plan_Id->distributorPlan)) {
            $arrayData = [];
            if (gettype($to_dist_plan_Id['distributorPlan']->pdo_id) == 'string') {
                $arrayData[] = $to_dist_plan_Id['distributorPlan']->pdo_id;
            } else {
                $arrayData = $to_dist_plan_Id['distributorPlan']->pdo_id;
            }
            if (in_array($from_PDO_planId, json_decode(json_encode($arrayData), true))) {
                $to_distId = Distributor::where('owner_id', $to)->first()->id;
                $to_dist_pincode = DistributorLocations::where('dist_id', $to_distId)->pluck('selected_area')->toArray();
                $from_PDO_pincode = Profile::where('user_id', $from)->pluck('postal_code')->toArray()[0];

                if (in_array($from_PDO_pincode, $to_dist_pincode)) {
                    $assignRequest = AssignPdoRequest::where('from_id', $from)->first();

                    if ($user->isAdmin()) {
                        $updatePdoOwner = User::where('id', $from)->update([
                            'is_parentId' => $to,
                        ]);

                        if ($assignRequest) {
                            $assignRequest->update([
                                'from_id' => $from,
                                'to_id' => $to,
                                'status' => $assign_status,
                                'user_id' => $to
                            ]);
                        } else {
                            $assignRequest = AssignPdoRequest::create([
                                'from_id' => $from,
                                'to_id' => $to,
                                'status' => $assign_status,
                                'user_id' => $to
                            ]);
                        }

                    } else {
                        if ($assignRequest) {
                            $assignRequest->update([
                                'from_id' => $from,
                                'to_id' => $to,
                                'status' => $assign_status,
                                'user_id' => $to
                            ]);
                        } else {
                            $assignRequest = AssignPdoRequest::create([
                                'from_id' => $from,
                                'to_id' => $to,
                                'status' => $assign_status,
                                'user_id' => $to
                            ]);
                        }
                    }
                    return $this->index_with_payouts();
                } else {
                    return response()->json(['message' => 'Locations not matched ...'], 404);
                }
            } else {
                return response()->json(['message' => 'Distributor details or PDO details not fetching ...'], 404);
            }
        } else {
            return response()->json(['message' => 'PDO Plan are not matched ...'], 404);
        }

    }

    public function pdo_request_list()
    {
        $data = AssignPdoRequest::with(['fromUser', 'toUser'])->get();
        $response = [];
        foreach ($data as $key => $value) {
            $nestedData = array();
            $nestedData['id'] = $value->id;
            $nestedData['pdo_name'] = "{$value->fromUser->first_name} {$value->fromUser->last_name} / {$value->fromUser->email}";
            $nestedData['assigner_name'] = "{$value->toUser->first_name} {$value->toUser->last_name} / {$value->toUser->email}";
            $nestedData['status'] = $value->status ? 1 : 0;
            $response[] = $nestedData;
        }
        return $response;
    }

    public function pdo_request_status($id)
    {
        // $data = AssignPdoRequest::find($id);
        // $from_id = $data->from_id;
        // $to_id = $data->to_id;
        // $update_data = $data->update([
        //     'status' => 1
        // ]);
        // if($update_data) {
        //     $pdo = User::find($from_id)->update([
        //         'is_parentId' =>$to_id
        //     ]);
        // }
        // return $this->pdo_request_list();
        $data = AssignPdoRequest::find($id);

        if ($data) {
            $from_id = $data->from_id;
            $to_id = $data->to_id;

            // Use a transaction to combine both updates into one database query
            try {
                DB::beginTransaction();

                // Update the AssignPdoRequest record
                $data->update([
                    'status' => 1
                ]);

                // Update the User record
                $pdo = User::find($from_id)->update([
                    'is_parentId' => $to_id
                ]);

                DB::commit();
            } catch (\Exception $e) {
                DB::rollback();
            }
        }

        return $this->pdo_request_list();

    }

    public function add_credits(Request $request) {
        $pdoId = $request->id;
        $credits = $request->credits;
        $user = User::where('id', $pdoId)->first();
        $pdo_plan = PdoaPlan::where('id', $user->pdo_type)->first();
        $graceCredits = PdoCredits::where('pdo_id', $pdoId)
            ->where('type', 'grace-credits')
            ->orderBy('created_at', 'desc')
            ->first();

        $noRecordsAfterGrace = "";

        if ($graceCredits) {
            $graceCreationDate = $graceCredits->created_at;
            $graceExpirationDate = $graceCredits->expiry_date;

            $noRecordsAfterGrace = PdoCredits::where('pdo_id', $pdoId)
                ->where(function ($query) use ($graceCreationDate, $graceExpirationDate) {
                    $query->where('expiry_date', '<', $graceExpirationDate)
                        ->where('created_at', '<', $graceCreationDate)
                        ->where(function ($query) {
                            $query->whereNull('type')
                                ->orWhere('type', 'add-on');
                        });
                })->get();

        }

        if ($pdoId !== null) {
            if ($noRecordsAfterGrace == "") {
                if ($request->credits) {
                    $newcredits = [
                        'pdo_id' => $pdoId,
                        'credits' => $credits,
                        'used_credits' => $graceCredits->used_credits ?? 0,
                        'type' => 'add-on',
                        'grace_credits' => $graceCredits->used_credits ?? 0
                    ];
                    PdoCredits::create($newcredits);
                    $credits_latest = PdoCredits::where('pdo_id', $pdoId)->orderBy('created_at', 'desc')->first();

                    if ($credits_latest) {
                        $expiry_date = $credits_latest->created_at->addMonths($pdo_plan->validity_period);
                        $credits_latest->expiry_date = $expiry_date;
                        $credits_latest->save();
                    }

                    PdoCreditsHistory::create(['pdo_id' => $pdoId, 'credits' => $credits]);
                    $credit_history = PdoCreditsHistory::where('pdo_id', $pdoId)->orderBy('created_at', 'desc')->first();
                    event(new PdoAddCreditsEvents($user, $credit_history));
                    return response()->json(['message' => 'Add credits Successfully'], 200);
                }
            } else {
                // Add credit logic if there are records after grace
                // (similar to the first condition, ensure it handles credits appropriately)
            }

            if ($request->add_on_sms) {
                $pdo_sms_quota = PdoSmsQuota::where('pdo_id', $pdoId)->first();
                $data_sms_quota = ['pdo_id' => $pdoId, 'sms_quota' => $request->add_on_sms, 'type' => 'add-on'];
                PdoSmsQuota::create($data_sms_quota);

                $data_history = ['pdo_id' => $pdoId, 'sms_credits' => $request->add_on_sms, 'type' => 'add-on'];
                PdoAddOnSmsHistory::create($data_history);

                $add_sms = PdoAddOnSmsHistory::where('pdo_id', $pdoId)->orderBy('created_at', 'desc')->first();
                event(new PdoAddSmsEvents($user, $add_sms));
                return response()->json(['message' => 'Add on SMS Successfully'], 200);
            }
        } else {
            return response()->json(['message' => 'Invalid request, pdo_id not provided'], 400);
        }
    }


    public function activeAutoRenew(Request $request)
    {
        $id = $request->pdo;
        $user = User::find($id);
        $pdoCredits = PdoCredits::where('pdo_id', $id)->first();

        if (!$user || !$pdoCredits) {
            return response()->json([
                'message' => 'User or PDO credits not found',
            ], 404);
        }

        if ($user->role == '1' && $pdoCredits->credits > 0) {
            $pdoCredits->used_credits = $pdoCredits->used_credits !== null ? $pdoCredits->used_credits + 1 : 1;
            $pdoCredits->save();
            $user->auto_renew_subscription = 1;
            $user->save();
            return response()->json([
                'message' => 'Auto-renewal activated successfully',
            ], 200);
        } else {
            return response()->json([
                'message' => 'Auto-renewal conditions not met',
            ], 400);
        }
    }

    public function disableAutoRenew(Request $request)
    {
        $id = $request->pdo;
        $user = User::find($id);
        $pdoCredits = PdoCredits::where('pdo_id', $id)->first();

        if (!$user || !$pdoCredits) {
            return response()->json([
                'message' => 'User or PDO credits not found',
            ], 404);
        }

        if ($user->role == '1' && $pdoCredits->credits > 0) {
            $pdoCredits->used_credits = $pdoCredits->used_credits !== null ? $pdoCredits->used_credits + 1 : 1;
            $pdoCredits->save();
            $user->auto_renew_subscription = 0;
            $user->save();
            return response()->json([
                'message' => 'Auto-renewal disabled successfully',
            ], 200);
        } else {
            return response()->json([
                'message' => 'Auto-renewal conditions not met',
            ], 400);
        }
    }

    public function subscriptionPlanDetails()
    {
       $user = Auth::user();

        if (!$user) {
            return response()->json(['message' =>'User not found'], 404);
        }
        // Fetch subscription plan details
        $subs_plan_details = PdoaPlan::where('id', $user->pdo_type)->first();

        if ($subs_plan_details) {
            $pdo_sms_details = PdoSmsQuota::where('pdo_id', $user->id)->latest('created_at')->first();
            $pdo_credits_details = PdoCredits::where('pdo_id', $user->id)->latest('created_at')->first();
            $pdo_credits_history = PdoCreditsHistory::where('pdo_id',$user->id)->get();
            $pdo_sms_history = PdoAddOnSmsHistory::where('pdo_id',$user->id)->get();

            $response = [
                'subscription_plan' => $subs_plan_details ?? null,
                'pdo_sms_details' => $pdo_sms_details,
                'pdo_credits_details' => $pdo_credits_details,
                'pdo_credits_history' => $pdo_credits_history,
                'pdo_sms_history'   =>$pdo_sms_history

            ];

            return response()->json($response, 200);
        } else {
            return response()->json(['message' =>'Subscription plan details not found'], 404);
        }
    }

    public function creditHistory()
    {
        $user = Auth::user();
        $data_arr = [];
        if (!$user) {
            return response()->json(['message' =>'User not found'], 404);
        }
        // Fetch credits details
        $pdo_credits_details = PdoCredits::where('pdo_id', $user->id)->get();
        if ($pdo_credits_details) {
            foreach ($pdo_credits_details as $pdo_credits_detail) {
                $id = $pdo_credits_detail->id;
                $credits = $pdo_credits_detail->credits ?? 0;
                $type = $pdo_credits_detail->type;
                $expiry_date = $pdo_credits_detail->expiry_date;
                $used_credits = $pdo_credits_detail->used_credits;
                $created_at = $pdo_credits_detail->created_at;

                $data_arr[] = [
                    'id'    =>  $id,
                    'credits' => $credits,
                    'type' =>$type,
                    'used_credits' =>$used_credits,
                    'expiry_date' => $expiry_date,
                    'created_at' => $created_at,

                ];
            }
            $response = [
                'data' =>$data_arr

            ];
            return response()->json($response, 200);
        } else {
            return response()->json(['message' =>'Pdo credits details not found'], 404);
        }
    }

    public function smsHistory()
  {
      $user = Auth::user();
      $data_arr = [];
      if (!$user) {
          return response()->json(['message' =>'User not found'], 404);
      }
      // Fetch credits details
      $pdo_sms_details =PdoSmsQuota::where('pdo_id', $user->id)->orderBy('created_at','desc')->get();

      if ($pdo_sms_details) {
          foreach ($pdo_sms_details as $pdo_sms_detail) {
              $id = $pdo_sms_detail->id;
              $sms_quota = $pdo_sms_detail->sms_quota ?? 0;
              $default_sms = $pdo_sms_detail->default_sms ?? 0;
              $total_sms = $sms_quota + $default_sms;
              $add_on_sms = $pdo_sms_detail->add_on_sms ?? 0 ;
              $carry_forward = $pdo_sms_detail->carry_forward_sms ?? 0 ;
              $created = $pdo_sms_detail->created_at;
              $sms_used = $pdo_sms_detail->sms_used ?? 0;

              $data_arr[] = [
                  'id'  =>  $id,
                  'total_sms' => $total_sms,
                  'add_on_sms' => $add_on_sms,
                  'carry_forward' => $carry_forward,
                  'created_at' => $created,
                  'sms_used' => $sms_used,

              ];
          }

          $response = [
              'data' =>$data_arr

          ];
          return response()->json($response, 200);
      } else {
          return response()->json(['message' =>'Pdo credits details not found'], 404);
      }
  }

    public function smsCount()
    {
        $user = Auth::user();
        $response = [];

        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        // Fetch credits details
        $pdo_sms_quota = PdoSmsQuota::where('pdo_id', $user->id)->latest()->first();

        $total_sms = 0;
        $total_sms_used = 0;

        if ($pdo_sms_quota) {
                $sms = $pdo_sms_quota->sms_quota ?? 0;
                $default_sms = $pdo_sms_quota->default_sms ?? 0;
                $add_on_sms = $pdo_sms_quota->add_on_sms;
                $carry_forward_sms = $pdo_sms_quota->carry_forward_sms ?? 0;
                $total_sms = $sms + $default_sms + $add_on_sms + $carry_forward_sms;
                $total_sms_used += $pdo_sms_quota->sms_used ?? 0;

        }

        $response = [
            'total_sms' => $total_sms,
            'total_sms_used' => $total_sms_used,
        ];

        return response()->json($response);
    }

    public function creditsCount()
  {
      $user = Auth::user();

      $response = [];

      if (!$user) {
          return response()->json(['message' =>'User not found'], 404);
      }
      // Fetch credits details
      $pdo_credits = PdoCredits::where('pdo_id', $user->id)->latest()->first();

      $total_credit = 0;
      $total_used_credits = 0;

      if ($pdo_credits->isNotEmpty()) {
          foreach ($pdo_credits as $credit) {
              $total_credit += $credit->credits;
              $total_used_credits += $credit->used_credits;
          }
      }

      $response = [
          'total_credit' => $total_credit,
          'total_used_credits' => $total_used_credits,
      ];

      return response()->json($response);
  }

    public function usedCreditHistory(Request $request) {
      $user = Auth::user();
      $data_arr = [];
      if (!$user) {
          return response()->json(['message' =>'User not found'], 404);
      }
      // Fetch credits details
      $usedCreditHistory = CreditsHistoy::where('pdo_credits_id', $request->id)->get();
      $pdoCredits = PdoCredits::where('id', $request->id)->first();

      if ($usedCreditHistory) {
          foreach ($usedCreditHistory as $usedCredit) {
              $router = Router::where('id', $usedCredit->router_id)->pluck('mac_address');
              $type = $usedCredit->type;
              $date = $usedCredit->created_at;
              $creditsUsed = $usedCredit->credit_used;

              $data_arr[] = [
                  'router' => $router,
                  'type' => $type,
                  'date' => $date,
                  'credits_used' => $creditsUsed,
              ];
          }
          $response = [
              'data' => $data_arr,
              'pdoCredits' => $pdoCredits

          ];
          return response()->json($response,  200);
      }

      return response()->json( 404);

  }

    public function usedSmsHistory(Request $request) {
      $user = Auth::user();
      $data_arr = [];
      if (!$user) {
          return response()->json(['message' =>'User not found'], 404);
      }
      // Fetch credits details
      $usedSmsCreditHistory = SmsHistory::where('quota_id', $request->id)->get();
      $pdoSmsCredits = PdoSmsQuota::where('id', $request->id)->first();

      if ($usedSmsCreditHistory) {
          foreach ($usedSmsCreditHistory as $usedSmsCredit) {
              $locationId = User::where('phone', $usedSmsCredit->phone)
                  ->orderBy('created_at', 'desc')
                  ->value('location_id');

              $location = "";

              if($locationId) {
                  $location = Location::where('id', $locationId)->first();
              }
              $phone = $usedSmsCredit->phone ? $maskedPhone = substr_replace($usedSmsCredit->phone, '******', 2, -2) : "";
              $date = $usedSmsCredit->created_at;

              $data_arr[] = [
                  'phone' => $phone,
                  'date' => $date,
                  'location' => $location->name
              ];
          }
          $response = [
              'data' => $data_arr,
              'pdoSmsCredits' => $pdoSmsCredits

          ];
          return response()->json($response,  200);
      }

      return response()->json( 404);

  }

  function destroy($id){

    try{
        $isApAssigned = Router::where("owner_id",$id)->exists();

        if ($isApAssigned) {
            return response()->json([
                'message' => 'Inventory has been assigned to the PDO Account, cannot delete.'
            ], Response::HTTP_NON_AUTHORITATIVE_INFORMATION); //203
        }

        $pdoAcc = User::locationOwners()->where("id", $id)->first();

        $pdoAcc->delete();

        return response()->json([
            'message' => 'PDO Account deleted successfully.',
        ],200);
    }
    catch(\Exception $e){
        Log::error('Issue on Deleting PDO Account', ['error' => $e->getMessage()]);
        return response()->json(['message' => 'Something Went Deleting PDO Account', 'error' => $e->getMessage()], 500);
    }

}


}
