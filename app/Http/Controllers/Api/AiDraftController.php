<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\DeepSeekParserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AiDraftController extends Controller
{
    public function __construct(
        private readonly DeepSeekParserService $parser,
    ) {}

    public function parse(Request $request): JsonResponse
    {
        $data = $request->validate([
            'message' => 'required|string|max:10000',
        ]);

        return response()->json(
            $this->parser->parseIntent($data['message'], $request->user()->company_id)
        );
    }
}
