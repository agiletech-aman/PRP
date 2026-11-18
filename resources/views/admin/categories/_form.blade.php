<x-form-section title="Where it applies" description="Common headings appear on every department's form. A department KRA appears only on that department's form.">
    <x-field name="department_id" label="Applies to" full>
        <select id="department_id" name="department_id">
            <option value="">All departments (common heading)</option>
            @foreach ($departments as $department)
                <option value="{{ $department->id }}" @selected((string) old('department_id', $category->department_id) === (string) $department->id)>
                    Only {{ $department->name }} (department KRA)
                </option>
            @endforeach
        </select>
    </x-field>
</x-form-section>

<x-form-section title="Heading details" description="Code and order decide how it is listed on the appraisal form.">
    <x-field name="code" label="Code" required hint="Short label like A, B or K.">
        <input id="code" type="text" name="code" value="{{ old('code', $category->code) }}" placeholder="e.g. A" maxlength="10" required>
    </x-field>
    <x-field name="sort_order" label="Display order" required hint="Lower numbers appear first.">
        <input id="sort_order" type="number" name="sort_order" min="0" value="{{ old('sort_order', $category->sort_order ?? 0) }}" required>
    </x-field>
    <x-field name="name" label="Heading" required full>
        <input id="name" type="text" name="name" value="{{ old('name', $category->name) }}" placeholder="e.g. Approach to work" required>
    </x-field>
    <div class="field-full">
        <x-toggle name="is_active" label="Active" hint="Inactive headings are hidden from appraisal forms." :checked="old('is_active', $category->is_active)" />
    </div>
</x-form-section>
