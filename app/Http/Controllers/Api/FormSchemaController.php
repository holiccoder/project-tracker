<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\InputSchemaRegistry;
use Illuminate\Http\JsonResponse;

class FormSchemaController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(InputSchemaRegistry::contract());
    }
}
