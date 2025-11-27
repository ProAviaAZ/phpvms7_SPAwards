<?php

namespace Modules\Awards\Awards;

use App\Contracts\Award;
use App\Models\Award as AwardModel;
use App\Models\UserAward;
use Illuminate\Support\Facades\Log;

class SPAwardsRequiredAwards extends Award
{
    public $name = 'SPAwards(RequiredAwards)';

    public $param_description = 'Comma-separated list of award IDs required to grant this award, e.g. "2,4,6"';

    public function check($awardIdsParam = null): bool
    {
        // Ensure parameter is provided and valid
        if (is_null($awardIdsParam) || trim($awardIdsParam) === '') {
            Log::error('SPAwards(RequiredAwards) | No parameter set.');
            return false;
        }

        // Check if the award is already granted
        $award = AwardModel::where('ref_model', get_class($this))
            ->where('ref_model_params', (string) $awardIdsParam)
            ->first();

        if (!$award) {
            Log::error("SPAwards(RequiredAwards) | No matching award found for params '{$awardIdsParam}'.");
            return false;
        }

        // Check if this award has already been granted to the pilot
        $alreadyGranted = UserAward::where('user_id', $this->user->id)
            ->where('award_id', $award->id)
            ->exists();

        if ($alreadyGranted) {
            Log::info("SPAwards(RequiredAwards) | Award already granted to Pilot (ID: {$this->user->id}). Skipping...");
            return false;
        }

        // Convert the parameter into a list of award IDs
        $ids = array_filter(
            array_map(
                static function ($value) {
                    return (int) trim($value);
                },
                explode(',', $awardIdsParam)
            ),
            static function ($value) {
                return $value > 0;
            }
        );

        if (empty($ids)) {
            Log::error("SPAwards(RequiredAwards) | Invalid parameter '{$awardIdsParam}'. Expected a comma-separated list of positive integers, e.g. '2,4,6'.");
            return false;
        }

        // Count how many required awards exist in total
        $requiredCount = count($ids);

        // Count how many of those awards the pilot already owns
        $grantedCount = UserAward::where('user_id', $this->user->id)
            ->whereIn('award_id', $ids)
            ->count();

        // Log for debugging
        Log::info("SPAwards(RequiredAwards) | Pilot (ID: {$this->user->id}) has {$grantedCount} of {$requiredCount} required awards (" . implode(',', $ids) . ").");

        // Return true if the pilot meets or exceeds the required flights
        return $grantedCount === $requiredCount;
    }
}
