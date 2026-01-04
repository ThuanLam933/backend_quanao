<?php

namespace App\Http\Controllers;

use App\Models\ProductDiscount;
use Illuminate\Http\Request;

class ProductDiscountController extends Controller
{
    
    public function index()
    {
        return ProductDiscount::orderByDesc('id')->get();
    }

    
    public function store(Request $request)
    {
        $data = $request->validate([
            'type'     => 'required|in:percent,fixed',
            'value'    => 'required|numeric|min:0',
            'start_at' => 'nullable|date',
            'end_at'   => 'nullable|date|after:start_at',
            'is_active'=> 'boolean',
        ]);

        return ProductDiscount::create($data);
    }

    
    public function show(ProductDiscount $productDiscount)
    {
        return $productDiscount->load('productDetails');
    }

    public function update(Request $request, ProductDiscount $productDiscount)
    {
        $data = $request->validate([
            'type'     => 'sometimes|in:percent,fixed',
            'value'    => 'sometimes|numeric|min:0',
            'start_at' => 'nullable|date',
            'end_at'   => 'nullable|date|after:start_at',
            'is_active'=> 'boolean',
        ]);

        $productDiscount->update($data);

        return $productDiscount;
    }

    public function destroy(ProductDiscount $productDiscount)
    {
        $productDiscount->delete();
        return response()->noContent();
    }
}
