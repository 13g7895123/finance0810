<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use App\Models\CustomField;
use App\Models\CustomFieldValue;

class CustomFieldController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth:api', 'role:admin|executive|manager']);
    }

    public function index(Request $request)
    {
        $entityType = $request->query('entity_type');
        $query = CustomField::query();
        if ($entityType) {
            $query->where('entity_type', $entityType);
        }
        return $query->orderBy('sort_order')->get();
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'entity_type' => 'required|string|in:customer,lead,case',
            'key' => 'required|string|max:100',
            'label' => 'required|string|max:150',
            'type' => 'nullable|string|max:50',
            'options' => 'nullable|array',
            'is_required' => 'nullable|boolean',
            'is_filterable' => 'nullable|boolean',
            'is_visible' => 'nullable|boolean',
            'group' => 'nullable|string|max:100',
            'sort_order' => 'nullable|integer',
            'validation_rules' => 'nullable|array',
            'default_value' => 'nullable|string',
        ]);
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();
        $field = CustomField::create($data);
        return response()->json(['message' => 'created', 'field' => $field], 201);
    }

    public function update(Request $request, CustomField $field)
    {
        $validator = Validator::make($request->all(), [
            'label' => 'sometimes|string|max:150',
            'type' => 'sometimes|string|max:50',
            'options' => 'nullable|array',
            'is_required' => 'nullable|boolean',
            'is_filterable' => 'nullable|boolean',
            'is_visible' => 'nullable|boolean',
            'group' => 'nullable|string|max:100',
            'sort_order' => 'nullable|integer',
            'validation_rules' => 'nullable|array',
            'default_value' => 'nullable|string',
        ]);
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        $field->fill($validator->validated())->save();
        return response()->json(['message' => 'updated', 'field' => $field]);
    }

    public function destroy(CustomField $field)
    {
        $field->delete();
        return response()->json(['message' => 'deleted']);
    }

    // 設定或更新某 entity 的欄位值
    public function setValue(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'entity_type' => 'required|in:customer,lead,case',
            'entity_id' => 'required|integer',
            'key' => 'required|string',
            'value' => 'nullable',
        ]);
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        $data = $validator->validated();

        $field = CustomField::where('entity_type', $data['entity_type'])
            ->where('key', $data['key'])
            ->firstOrFail();

        $record = CustomFieldValue::updateOrCreate([
            'entity_type' => $data['entity_type'],
            'entity_id' => $data['entity_id'],
            'field_id' => $field->id,
        ], [
            'value' => is_array($data['value']) ? json_encode($data['value']) : (string)($data['value'] ?? ''),
            'updated_by' => Auth::id(),
        ]);

        return response()->json(['message' => 'saved', 'value' => $record]);
    }
}
