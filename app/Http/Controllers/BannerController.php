<?php

namespace App\Http\Controllers;


use App\Http\Requests\CreateBannerRequest;
use App\Models\Banners;
use App\Models\BrandingProfile;
use App\Models\Location;
use App\Models\ModelFirmwares;
use App\Models\PdoBannerImpressions;
use App\Models\PdoLocationZoneBannerAds;
use App\Models\User;
use App\Models\UserImpression;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use function PHPUnit\Framework\isEmpty;

class BannerController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->all();

        return Banners::create($data);
    }

    public function bannerStore(CreateBannerRequest $form)
    {
        return $form->save();
    }

    public function bannerUpdate($id, CreateBannerRequest $form)
    {
        $banner = $form->updateBanner($id);

        if ($banner) {
            return response()->json(['banner' => $banner], 200);
        } else {
            return response()->json(['message' => 'Failed to update banner'], 500);
        }
    }

    public function index()
    {
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'];
        $url = "{$protocol}://{$host}";
        $user = Auth::user();

        if ($user->parent_id) {
            $parent = User::find($user->parent_id);
            $user = $parent;
        }

        /*$banners = Banners::select(
            'banners.id',
            'banners.pdo_id',
            'banners.created_at',
            'banners.image',
            'banners.title',
            'banners.description',
            'banners.video',
            'banners.expiry_date',
            'banners.status',
            DB::raw("CONCAT('$url/banners/', banners.image) as image_url"),
            DB::raw("CONCAT('$url/advertisement/', banners.video) as video_url")
        )
            ->where('banners.pdo_id', $user->id)
            ->where('banners.suspend', false)
            ->with(['impressions'])
            ->with(['clicks'])
            ->orderBy('banners.created_at', 'desc')
            ->get();*/
        $banners = Banners::select(
            'banners.id',
            'banners.pdo_id',
            'banners.created_at',
            'banners.image',
            'banners.title',
            'banners.description',
            'banners.video',
            'banners.expiry_date',
            'banners.status',
            'banners.suspend',
            DB::raw("CONCAT('$url/banners/', banners.image) as image_url"),
            DB::raw("CONCAT('$url/advertisement/', banners.video) as video_url")
        )
            ->where('banners.pdo_id', $user->id)
            ->whereDate('banners.expiry_date', '>=', Carbon::today())
            ->where('banners.suspend', false)
            ->with(['impressions'])
            ->with(['clicks'])
            ->orderBy('banners.created_at', 'desc')
            ->get();
        //dd($banners);
        // Process each banner to include total impressions

        if ($banners) {
            foreach ($banners as $banner) {
                $totalImpressions = $banner->impressions->sum('impressions');
                $totalClicks = $banner->clicks->sum('clicks');
                $banner->total_impressions = $totalImpressions;
                $banner->total_clicks = $totalClicks;
            }

            return response()->json(['banners' => $banners]);
        } else {
            return response()->json(['message' =>'no banners'],200);
        }
    }

    public function getAdvertisementDetails($id)
    {
        $banner = Banners::findOrFail($id);
        $locationZoneBannerAds = $banner->locationZoneBannerAds;

        $banner = Banners::with('locationZoneBannerAds')->findOrFail($id);
        $locationZoneBannerAds = $banner->locationZoneBannerAds;

        $locationIds = $locationZoneBannerAds->pluck('location_id');
        $zoneIds = $locationZoneBannerAds->pluck('zone_id');

        $locationDetails = Location::whereIn('id', $locationIds)->get();
        $zoneDetails = BrandingProfile::whereIn('id', $zoneIds)->get();
        $locationDetailsArray = $locationDetails->toArray();
        $zoneDetailsArray = $zoneDetails->toArray();

        return response()->json([
            'banner' => $banner,
            'location' => $locationDetailsArray,
            'zone' => $zoneDetailsArray
        ], 200);

    }

    public function deleteAdvertisement($id)
    {
        $banner = Banners::where('id', $id)->first();
        if ($banner) {

            $banner->delete();
            return response()->json([
                'branding_profile' => $id,
                'message' => 'Advertisement deleted successfully'
            ], 200);
        }
        return response()->json([
            'banner' => $id,
            'message' => 'Advertisement does not exist'
        ], 200);
    }
    public function advertisement(Request $request)
    {
        $banner = Banners::where('id', $request->id)->first();

        if ($banner) {
            $banner->status = $request->status;
            $banner->save();
            return response()->json(['message' => 'Advertisement status updated successfully'], 200);
        } else {
            return response()->json(['error' => 'Advertisement not found'], 404);
        }
    }

    public function addCaptivePortal(Request $request)
    {

        $banner = Banners::where('id', $request->banner_id)->first();
        if ($banner) {
            $type = $banner->video ? 'video' : ($banner->image ? 'image' : 'text');

            $existingImpression = PdoBannerImpressions::where('location_id', $request->location_id)
                ->where('pdo_banner_id', $banner->id)
                ->where('type', $type)
                ->first();

            if ($existingImpression) {
                $existingImpression->impressions++;
                $existingImpression->save();
            } else {

                $existingDifferentTypeImpression = PdoBannerImpressions::where('location_id', $request->location_id)
                    ->where('pdo_banner_id', $banner->id)
                    ->first();

                if ($existingDifferentTypeImpression && $existingDifferentTypeImpression->type !== $type) {

                    $newImpression = new PdoBannerImpressions();
                    $newImpression->impressions = 1;
                    $newImpression->type = $type;
                    $newImpression->location_id = $request->location_id;
                    $newImpression->zone_id = $locationRandom->zone_id ?? 0;
                    $newImpression->pdo_banner_id = $banner->id;
                    $newImpression->save();
                } else {

                    $newImpression = new PdoBannerImpressions();
                    $newImpression->impressions = 1;
                    $newImpression->type = $type;
                    $newImpression->location_id = $request->location_id;
                    $newImpression->zone_id = $locationRandom->zone_id ?? 0;
                    $newImpression->pdo_banner_id = $banner->id;
                    $newImpression->save();
                }
            }
        }
        return response()->json(['banner' => $banner],200);
        /* foreach ($locationRandom as $location) {
             $banners = $location->banners;
             $locationBanners[] = [
                 'location' => $location,
                 'banners' => $banners,
             ];
             $PdoBannerImpressions = new PdoBannerImpressions();
             $PdoBannerImpressions->impression_counts++;
             $PdoBannerImpressions->location_id = $locations->location_id;
             $PdoBannerImpressions->zone_id = $locations->location_id;
             $PdoBannerImpressions->pdo_banner_id = $banners->id;
             $PdoBannerImpressions->save();
         }*/
    }

    public function saveClick(Request $request)
    {
        $clickCount = $request->input('clicks');
        $locationId = $request->input('location_id');
        $elementId = $request->input('elementId');
        $pdoBannerId = $request->input('bannerId');

        $type = '';
        if ($elementId == 'video-add') {
            $type = 'video';
        } elseif ($elementId == 'link-Image') {
            $type = 'image';
        } elseif ($elementId == 'link') {
            $type = 'text';
        }
        $click = PdoBannerImpressions::where('location_id', $locationId)

            ->where('type', $type)->where('pdo_banner_id',$pdoBannerId)
            ->first();
        if ($click) {
            $click->clicks += 1;
            $click->save();
        } else {
        }
        $banner = Banners::where('id', $pdoBannerId)->first();
        if ($banner) {
            $banner->clicks_count += 1 ;
            $banner->save();

            if($banner->clicks == $click->clicks) {
                $suspend = Banners::where('id' ,$pdoBannerId)->update(['suspend' =>true]);
            }

        } else {
        }
        return response()->json(['message' => 'Click data saved successfully'], 200);
    }

    public function pageType(Request $request)
    {
        $locations = PdoLocationZoneBannerAds::where('location_id', $request->location_id)
            ->whereNull('zone_id')
            ->with(['banners' => function ($query) {
                $query->where('status', 1)
                    ->where(function ($q) {
                        $q->where('suspend', false)
                            ->whereDate('expiry_date', '>=', Carbon::today());
                    });
            }])
            ->get();
        $locationBanners = [];
        if ($locations->isNotEmpty()) {
            $locationRandom = $locations->random();
            $banners = $locationRandom->banners;
            foreach ($banners as $banner) {
                $locationBanners[] = [
                    'location' => $locationRandom,
                    'banners' => $banner,
                ];
            }
        }
        return response()->json($locationBanners);
    }

    public function userImpressions(Request $request)
    {
        $id = $request->bannerId;
        $macAddress = $request->macAddress;
        $user = Auth::user();
        $userId = $user->id ?? 0;
        $userImpression = UserImpression::where('user_id', $userId)->where('banner_id', $id)->first();
        if ($userImpression) {
            $userImpression->clicks += $request->clicks;
            $userImpression->save();
            return response()->json(['message' => 'User clicks and impressions updated successfully'], 200);
        } else {
            $userDetails = [
                'user_id' => $userId,
                'banner_id' => $id,
                'phone' => $user->phone ?? $request->macAddress,
                'mac_address' => $macAddress ?? '0',
                'impression' => 0,
                'clicks' => $request->clicks
            ];
            UserImpression::create($userDetails);
            return response()->json(['message' => 'User clicks and impressions saved successfully'], 200);
        }
    }

    public function userImpressionCount(Request $request)
    {
        $user = Auth::user();
        $id = $request->bannerId;
        $macAddress = $request->macAddress;
        $banner = Banners::findOrFail($id);
        if ($banner) {
            $banner->impression_counts += 1;
            $banner->save();
            if ($banner->impressions == $banner->impression_counts) {
                $suspend = Banners::where('id' ,$id)->update(['suspend' =>true]);
            }
        }
        $userId = $user->id ?? 0;
        $userImpressionCount = UserImpression::where('user_id', $userId)->where('banner_id', $id)->first();
        if ($userImpressionCount) {
            $userImpressionCount->impression += 1;
            $userImpressionCount->save();
            return response()->json(['message' => 'User clicks and impressions updated successfully'], 200);
        } else {
            $userDetails = [
                'user_id' => $userId,
                'banner_id' => $id,
                'phone' => $user->phone ?? $request->macAddress ?? '0',
                'mac_address' => $macAddress ?? '0',
                'impression' => 1,
                'clicks' => 0
            ];
            UserImpression::create($userDetails);
            return response()->json(['message' => 'User clicks and impressions saved successfully'], 200);
        }

    }
    public function suspendedAds()
    {
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'];
        $url = "{$protocol}://{$host}";
        $user = Auth::user();
        if ($user->parent_id) {
            $parent = User::find($user->parent_id);
            $user = $parent;
        }

        /* $banners = Banners::select(
             'banners.id',
             'banners.pdo_id',
             'banners.created_at',
             'banners.image',
             'banners.title',
             'banners.description',
             'banners.video',
             'banners.expiry_date',
             'banners.suspend',
             'banners.status',
             DB::raw("CONCAT('$url/banners/', banners.image) as image_url"),
             DB::raw("CONCAT('$url/advertisement/', banners.video) as video_url")
         )
             ->where('banners.pdo_id', $user->id)
             ->where('banners.suspend', true)
             ->with(['impressions'])
             ->with(['clicks'])
             ->orderBy('banners.created_at', 'desc')
             ->get();*/
        $banners = Banners::select(
            'banners.id',
            'banners.pdo_id',
            'banners.created_at',
            'banners.image',
            'banners.title',
            'banners.description',
            'banners.video',
            'banners.expiry_date',
            'banners.status',
            'banners.suspend',
            DB::raw("CONCAT('$url/banners/', banners.image) as image_url"),
            DB::raw("CONCAT('$url/advertisement/', banners.video) as video_url")
        )
            ->where('banners.pdo_id', $user->id)
            ->where(function($query) {
                $query->orWhereDate('banners.expiry_date', '<=', Carbon::today())
                    ->orWhere('banners.suspend', 1);
            })
            ->with(['impressions', 'clicks'])
            ->orderBy('banners.created_at', 'desc')
            ->get();
        //dd($banners);
        // Process each banner to include total impressions
        if ($banners) {
            foreach ($banners as $banner) {
                $totalImpressions = $banner->impressions->sum('impressions');
                $totalClicks = $banner->clicks->sum('clicks');
                $banner->total_impressions = $totalImpressions;
                $banner->total_clicks = $totalClicks;
            }

            return response()->json(['banners' => $banners]);
        } else {
            return response()->json(['banners' =>'no banners'],200);
        }
    }

    public function userDetails(Request $request)
    {
        $user_impressions = UserImpression::where('banner_id', $request->id)->get();
        if ($user_impressions->isNotEmpty()) {

            $user_data = [];

            foreach ($user_impressions as $impression) {
                $user = User::where('id' , $impression->user_id)->first();
                if($user && $user->location_id !== 0) {
                    $location = Location::where('id', $user->location_id)->first();
                }
                $user_data[] = [
                    'name' => $user->first_name ?? "NA",
                    'phone' => $impression->phone ?? "NA",
                    'impression' => $impression->impression,
                    'clicks' => $impression->clicks,
                    'location_name' => $location->name ?? "NA"
                ];
            }

            return response()->json(['message' => 'Getting user details successfully', 'user_details' => $user_data], 200);
        } else {
            return response()->json(['message' => 'No user details found'], 200);
        }
    }

    public function activeAds($id)
    {
        $banner = Banners::find($id);
        // Check if the banner exists
        if ($banner) {
            // Update the suspend value
            $banner->suspend = 0;
            // Save the updated banner
            $banner->save();
            // Return a success response
            return response()->json(['message' => 'Ads Active successfully'], 200);
        } else {
            // Return an error response if the banner is not found
            return response()->json(['message' => 'Ads not found'], 404);
        }
    }
}

