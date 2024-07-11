<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginCompanyRequest;
use App\Http\Requests\RegisterCompanyRequest;
use App\Http\Requests\StoreCompanyRequest;
use App\Models\Code;
use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Traits\VerificationTrait;
use Illuminate\Support\Facades\Log;
use App\Traits\UploadPhotoTrait;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CompanyController extends Controller
{
    use VerificationTrait, UploadPhotoTrait;
    public function register(RegisterCompanyRequest $request)
    {
        try {
            DB::beginTransaction();
            $validated = $request->validated();
            $validated['password'] = Hash::make($validated['password']);
            $company = Company::create($validated);
            $code = $this->sendCode($company);
            $token = $company->createToken('company')->plainTextToken;
            DB::commit();
            return response()->json([
                'company' => $company,
                'code' => $code,
                'token' => $token
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function checkCode(Request $request)
    {
        try {
            DB::beginTransaction();
            $user = Auth::user();
            if (isset($user->verified_at)) {
                return response()->json([
                    'messsage' => 'you are already verified'
                ]);
            }
            $validated = $request->validate([
                'code' => ['required', 'numeric', 'exists:codes,code']
            ]);
            $code = Code::where('code', $validated['code'])->where('email', $user->email)->first();
            if (!isset($code)) {
                return response()->json([
                    'message' => 'invalid code'
                ], 500);
            }
            if ($code->created_at > now()->addHour()) {
                $code->delete();
                return response()->json([
                    'message' => 'the code is outdated'
                ]);
            }
            $user->verified_at = now();
            $user->save();
            $code->delete();
            DB::commit();
            return response()->json([
                'message' => 'your email is verified'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function createProfile(StoreCompanyRequest $request)
    {
        try {
            $company = Auth::user();
            $validated = $request->validated();
            DB::beginTransaction();
            if($request->hasFile('photo')){
                $photo = $this->uploadPhoto($request,'photos',"companies");
                $company->photo()->create([
                    'name' => $photo
                ]);
            }
            unset($validated['photo']);
            // if ($request->hasFile('logo')) {
            //     $logoName = $request->file('logo')->hashName();
            //     $request->file('logo')->storeAs('logos', $logoName, 'companies');
            //     $validated['logo'] = $logoName;
            // }
            $company->update($validated);
            DB::commit();

            return response()->json(['message' => 'Profile created successfully', 'company' => $company]);
        } catch (\Throwable $th) {
            DB::rollBack();
            Log::error($th);

            return response()->json(['message' => 'An error occurred', 'error' => $th->getMessage()], 500);
        }
    }



    public function login(LoginCompanyRequest $request)
    {
        try {
            $validated = $request->validated();
            $company = Company::where('email', $validated['email'])->first();
            if (!Hash::check($validated['password'], $company->password)) {
                return response()->json([
                    'message' => 'wrong password!'
                ]);
            }
            $token = $company->createToken('company')->plainTextToken;
            return response()->json([
                'company' => $company,
                'token' => $token
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function logout(Request $request)
    {
        try {
            $request->user()->tokens()->delete();
            return response()->json(['message' => 'Successfully logged out']);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function myJobs()
    {
        try {
            // $freelancer = Freelancer::where('id',1)->first();
            $freelancer = Auth::user();
            $jobs = $freelancer->jobs;
            return response()->json([
                'jobs' => $jobs
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function myApplications()
    {
        try {
            $freelancer = Auth::user();
            $applications = $freelancer->applications;
            $jobs = [];
            foreach ($applications as $application) {
                $jobs[] = [$application->job];
            }
            return response()->json([
                'applications' => $jobs
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
