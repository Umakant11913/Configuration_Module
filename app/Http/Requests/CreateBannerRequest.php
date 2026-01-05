<?php

namespace App\Http\Requests;

use App\Models\Banners;
use App\Models\PdoLocationZoneBannerAds;
use App\Models\User;
use Carbon\Carbon;
use http\Env\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class CreateBannerRequest extends FormRequest
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
            'title' => 'required|string',
            //'video' => 'required|file|mimes:mp4', // Added validation for video

        ];

        return $rules;
    }

    public function save()
    {
        try {
            $user = Auth::user();

            if ($user->parent_id) {
                $parent = User::find($user->parent_id);
                if ($parent) {
                    $user = $parent;
                }
            }

            DB::beginTransaction();

            $folder = public_path('advertisement');
            if (!file_exists($folder)) {
                mkdir($folder, 0777, true);
            }

            // Create a new banner instance and fill it with data
            $originalFilename = $this->image ? $this->image->getClientOriginalName() : null;
            $originalVideoFilename = $this->video ? $this->video->getClientOriginalName() : null;
            $banner = new Banners();
            $data = $this->only('title', 'description', 'link','page_type', 'send_summary_report', 'summary_option', 'clicks');
            // Handle 'summary_option' if it's an array or object
            if (isset($data['summary_option'])) {
                if (is_array($data['summary_option']) || is_object($data['summary_option'])) {
                    $data['summary_option'] = json_encode($data['summary_option']);
                } else {
                    $data['summary_option'] = null;
                }
            } else {
                $data['summary_option'] = null;
            }
            $banner->fill($data);
            $banner->pdo_id = $user->id;
            $banner->impressions = $this->impressions;
            $banner->expiry_date = Carbon::parse($this->expiry_date)->format('Y-m-d H:i:s');

            $banner->impression_counts = 0;
            $banner->suspend = 0;
            $banner->image = $originalFilename;
            $banner->video = $originalVideoFilename;
            $banner->status = $this->status;

            if (!file_exists($folder)) {
                mkdir($folder, 0777, true);
            }
            if($this->description) {
                $banner->description = $this->description;
                $banner->video = null;
                $banner->image = null;
            }
            if ($this->image !=null) {
                $banner->image = $originalFilename;
                $banner->video = null;
            }
            if ($this->video !=null) {
                $banner->image = null;
                $banner->video = $originalVideoFilename;
            }

            $banner->save();
          //  dd($originalFilename);
            if ($this->image) {
                $banner->image = $originalFilename;
                $this->image->move($folder . '/' . $banner->id, $originalFilename);
            }

            if ($this->video) {
                $banner->video = $originalVideoFilename;
                $this->video->move($folder . '/' . $banner->id, $originalVideoFilename);
            }


            $locations =$this->location_id;
            $zones =$this->zone_id;

            $locations = collect($locations);
            $zones = collect($zones);

            if ($locations->isNotEmpty()) {
                foreach ($locations as $location) {
                    $pdoLocationZoneLocation = new PdoLocationZoneBannerAds();
                    $pdoLocationZoneLocation->pdo_banner_id = $banner->id;
                    $pdoLocationZoneLocation->location_id = $location;
                    $pdoLocationZoneLocation->save();
                }
            }
            if ($zones->isNotEmpty()) {
                foreach ($zones as $zone) {
                    $pdoLocationZoneZone = new PdoLocationZoneBannerAds();
                    $pdoLocationZoneZone->pdo_banner_id = $banner->id;
                    $pdoLocationZoneZone->zone_id = $zone;
                    $pdoLocationZoneZone->save();
                }
            }
            DB::commit();

            return $banner;

        } catch (Exception $e) {
            // Rollback the transaction if an exception occurs
            DB::rollBack();
            // Log the error
            Log::error($e->getMessage());
            // Return null or handle the error appropriately
            return null;
        }
    }

    public function updateBanner($id)
{

    try {
        $banner = Banners::find($id);
        $user = Auth::user();
        if ($user->parent_id) {
            $parent = User::find($user->parent_id);
            if ($parent) {
                $user = $parent;
            }
        }

        DB::beginTransaction();

        $folder = public_path('advertisement');
        if (!file_exists($folder)) {
            mkdir($folder, 0777, true);
        }

        // Create a new banner instance and fill it with data
        $originalFilename = $this->image ? $this->image->getClientOriginalName() : null;
        $originalVideoFilename = $this->video ? $this->video->getClientOriginalName() : null;
        $banner->fill($this->only('title', 'link','status', 'page_type', 'send_summary_report', 'summary_option', 'clicks'));
        $banner->pdo_id = $user->id;
        $banner->impressions = $this->impressions;
        $banner->expiry_date = Carbon::parse($this->expiry_date)->format('Y-m-d H:i:s');

        $banner->impression_counts = 0;
        $banner->status = $this->status ?? 0;

        if ($this->send_summary_report == 1) {
            $banner->send_summary_report = $this->send_summary_report;
            $banner->summary_option = $this->summary_option;
        } else {
            $banner->send_summary_report = NULL;
            $banner->summary_option = $this->summary_option;
        }

        // Only update image and video fields if they are provided
        if($this->description !=null) {
            $banner->description = $this->description;
            $banner->video = null;
            $banner->image = null;
        }
        if ($this->image !=null) {
            $banner->image = $originalFilename;
            $banner->description = null;
            $banner->video = null;
            $this->image->move($folder . '/' . $banner->id, $originalFilename);
        }

        if ($this->video !=null) {
            $banner->image = null;
            $banner->description = null;
            $banner->video = $originalVideoFilename;
            $this->video->move($folder . '/' . $banner->id, $originalVideoFilename);
        }

        $banner->save();
        $locations =$this->location_id;
        $zones =$this->zone_id;

        $locations = collect($locations);
        $zones = collect($zones);

        if ($locations->isNotEmpty()) {
            PdoLocationZoneBannerAds::where('pdo_banner_id', $banner->id)->whereNull('zone_id')->delete();

            foreach ($locations as $location) {
                $pdoLocationZoneLocation = new PdoLocationZoneBannerAds();
                $pdoLocationZoneLocation->pdo_banner_id = $banner->id;
                $pdoLocationZoneLocation->location_id = $location;
                $pdoLocationZoneLocation->save();
            }
        }

        if ($zones->isNotEmpty()) {
            PdoLocationZoneBannerAds::where('pdo_banner_id', $banner->id)->whereNull('location_id')->delete();

            foreach ($zones as $zone) {
                $pdoLocationZoneZone = new PdoLocationZoneBannerAds();
                $pdoLocationZoneZone->pdo_banner_id = $banner->id;
                $pdoLocationZoneZone->zone_id = $zone;
                $pdoLocationZoneZone->save();
            }
        }

      /*  $oldLocation = PdoLocationZoneBannerAds::where('pdo_banner_id', $banner->id)
            ->whereNull('zone_id')
            ->pluck('location_id')
            ->toArray();
        Log::info($oldLocation);
        $locationList = $this->get('location_id', []);
        $newLocations = array_diff($locationList, $oldLocation);
        $locationUpdate = implode($newLocations);

        Log::info($locationUpdate);
        //dd($newLocations);
        if ($locationUpdate){
          PdoLocationZoneBannerAds::where('pdo_banner_id', $banner->id)->whereNull('zone_id')->update(['location_id' => $locationUpdate]);
        }*/

       /* foreach ($locations as $location) {
            $pdoLocationZoneLocation = new PdoLocationZoneBannerAds(); // Create a new instance for each location
            $pdoLocationZoneLocation->pdo_banner_id = $banner->id;
            $pdoLocationZoneLocation->location_id = $location;
            $pdoLocationZoneLocation->save();
        }
        foreach ($zones as $zone ) {
            $pdoLocationZoneZone = new PdoLocationZoneBannerAds(); // Create a new instance for each zone
            $pdoLocationZoneZone->pdo_banner_id = $banner->id;
            $pdoLocationZoneZone->zone_id = $zone;
            $pdoLocationZoneZone->save();
        }*/


        DB::commit();

        return $banner;

    } catch (Exception $e) {
        // Rollback the transaction if an exception occurs
        DB::rollBack();
        // Log the error
        // Return null or handle the error appropriately
        return null;
    }
}

}
