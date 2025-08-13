<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Faq;
use App\Http\Resources\FaqResource;
use App\Http\Resources\TableResource;
use App\Http\Resources\PostResource;
use Illuminate\Validation\ValidationException;

class FaqController extends Controller
{
    public function index(Request $request)
    {
        $perPage = $request->get('per_page', 10);
        $faqs = Faq::orderBy('created_at', 'desc')->paginate($perPage);

        return new TableResource(
            true,
            'FAQ list retrieved successfully',
            ['data' => FaqResource::collection($faqs)],
            200
        );
    }

    public function indexPublic()
    {
        try {
            $faqs = Faq::orderBy('created_at', 'desc')->select('question', 'answer')->get();
            $data = $faqs->isNotEmpty() ? $faqs : [];
            return new PostResource(true, 'All FAQs retrieved successfully', $data);
        } catch (\Exception $e) {
            return new PostResource(false, 'Failed to retrieve FAQs: ' . $e->getMessage(), []);
        }
    }

    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'question' => 'required|string',
                'answer'   => 'required|string',
            ]);

            $faq = Faq::create($validated);

            return new PostResource(
                true,
                'FAQ created successfully',
                (new FaqResource($faq))->resolve($request)
            );
        } catch (ValidationException $e) {
            return new PostResource(false, $e->getMessage(), null);
        } catch (\Exception $e) {
            return new PostResource(false, 'Failed to create FAQ: ' . $e->getMessage(), null);
        }
    }

    public function show($id)
    {
        $faq = Faq::find($id);

        if (!$faq) {
            return new PostResource(false, 'FAQ not found', null);
        }

        return new PostResource(
            true,
            'FAQ retrieved successfully',
            (new FaqResource($faq))->resolve(request())
        );
    }

    public function update(Request $request, $id)
    {
        try {
            $faq = Faq::find($id);
            if (!$faq) {
                return new PostResource(false, 'FAQ not found', null);
            }

            $validated = $request->validate([
                'question' => 'required|string',
                'answer'   => 'required|string',
            ]);

            $faq->update($validated);

            return new PostResource(
                true,
                'FAQ updated successfully',
                (new FaqResource($faq))->resolve(request())
            );
        } catch (ValidationException $e) {
            return new PostResource(false, $e->getMessage(), null);
        } catch (\Exception $e) {
            return new PostResource(false, 'Failed to update FAQ: ' . $e->getMessage(), null);
        }
    }

    public function destroy($id)
    {
        $faq = Faq::find($id);

        if (!$faq) {
            return new PostResource(false, 'FAQ not found', null);
        }

        try {
            $faq->delete();
            return new PostResource(true, 'FAQ deleted successfully', null);
        } catch (\Exception $e) {
            return new PostResource(false, 'Failed to delete FAQ: ' . $e->getMessage(), null);
        }
    }
}