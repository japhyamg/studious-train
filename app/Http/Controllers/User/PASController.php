<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Services\ScreeningService;
use App\Models\ScreeningResult;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PASController extends Controller
{
    protected ScreeningService $screeningService;

    public function __construct(ScreeningService $screeningService)
    {
        $this->screeningService = $screeningService;
    }

    public function index()
    {
        return view('users.pas.index');
    }

    public function screen(Request $request)
    {
        set_time_limit(180);

        try {
            $validator = Validator::make($request->all(), [
                'first_name' => 'required',
                'entity_type' => 'required',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => implode(', ', $validator->errors()->all()),
                ]);
            }

            $results = $this->screeningService->screen($request->all());

            activity()->log('PAS screening performed for: ' . ($request->first_name . ' ' . $request->last_name));

            return response()->json([
                'status' => 'success',
                'data' => $results,
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'error' => $th->getMessage(),
            ], 400);
        }
    }
}
