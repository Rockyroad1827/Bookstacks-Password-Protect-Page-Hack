<?php
/** @var \BookStack\Permissions\PermissionFormData $data */

// --- CHECK STATUS ---
$isProtected = false;
$hasCustomPass = false;
$allowApi = false;

if($model instanceof \BookStack\Entities\Models\Page) {
    // Check for Protected tag
    $tag = $model->tags()->where('name', 'Protected')->first();
    $isProtected = !is_null($tag);
    $hasCustomPass = $isProtected && !empty($tag->value);

    // Check for AllowAPI tag
    $allowApi = $model->tags()->where('name', 'AllowAPI')->exists();
}
?>

{{-- MAIN PERMISSIONS FORM --}}
<form component="entity-permissions"
      option:entity-permissions:entity-type="{{ $model->getType() }}"
      action="{{ $model->getUrl('/permissions') }}"
      method="POST"
      id="main-permissions-form">
    {!! csrf_field() !!}
    <input type="hidden" name="_method" value="PUT">

    <div class="grid half left-focus v-end gap-m wrap">
        <div>
            <h1 class="list-heading">{{ $title }}</h1>
            <p class="text-muted mb-s">
                {{ trans('entities.permissions_desc') }}
                @if($model instanceof \BookStack\Entities\Models\Book)
                    <br> {{ trans('entities.permissions_book_cascade') }}
                @elseif($model instanceof \BookStack\Entities\Models\Chapter)
                    <br> {{ trans('entities.permissions_chapter_cascade') }}
                @endif
            </p>
        </div>
    </div>

    @if($model instanceof \BookStack\Entities\Models\Bookshelf)
        <p class="text-warn">{{ trans('entities.shelves_permissions_cascade_warning') }}</p>
    @endif

    <div class="flex-container-row justify-flex-end">
        <div class="form-group mb-m">
            <label for="owner">{{ trans('entities.permissions_owner') }}</label>
            @include('form.user-select', ['user' => $model->ownedBy, 'name' => 'owned_by'])
        </div>
    </div>

    <hr>

    <div refs="entity-permissions@role-container" class="item-list mt-m mb-m">
        @foreach($data->permissionsWithRoles() as $permission)
            @include('form.entity-permissions-row', [
                'permission' => $permission,
                'role' => $permission->role,
                'entityType' => $model->getType(),
                'inheriting' => false,
            ])
        @endforeach
    </div>

    <div class="flex-container-row justify-flex-end mb-xl">
        <div class="flex-container-row items-center gap-m">
            <label for="role_select" class="m-none p-none"><span
                        class="bold">{{ trans('entities.permissions_role_override') }}</span></label>
            <select name="role_select" id="role_select" refs="entity-permissions@role-select">
                <option value="">{{ trans('common.select') }}</option>
                @foreach($data->rolesNotAssigned() as $role)
                    <option value="{{ $role->id }}">{{ $role->display_name }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <div class="item-list mt-m mb-xl">
        @include('form.entity-permissions-row', [
                'role' => $data->everyoneElseRole(),
                'permission' => $data->everyoneElseEntityPermission(),
                'entityType' => $model->getType(),
                'inheriting' => !$model->permissions()->where('role_id', '=', 0)->exists(),
            ])
    </div>
    <hr class="mb-m">
    {{-- NATIVE-STYLE PIN CONTROL --}}
    @if($model instanceof \BookStack\Entities\Models\Page)
        
        {{-- CARD 1: MAIN LOCK CONTROL --}}
        <div class="mb-m mt-l">
            <div class="card p-m" style="border: 1px solid #444444; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
                <div class="flex-container-row justify-space-between items-center wrap gap-m">
                    
                    {{-- Left Side: Status Text --}}
                    <div style="flex: 1;">
                        <div class="flex-container-row items-center gap-xs">
                            @if($isProtected)
                                <span class="text-neg bold">@icon('lock') PIN Protection Active</span>
                            @else
                                <span class="text-pos bold">@icon('lock-open') PIN Protection Inactive</span>
                            @endif
                        </div>
                        <p class="text-muted small mb-none mt-xs">
                            @if($isProtected)
                                This page is locked. 
                                @if($hasCustomPass)
                                    (Using <strong>Custom</strong> Password)
                                @else
                                    (Using <strong>Master</strong> PIN)
                                @endif
                            @else
                                Restrict access to this page with a password.
                            @endif
                        </p>
                    </div>
                    
                    {{-- Right Side: Unlock/Lock Button --}}
                    <div>
                        @if($isProtected)
                            <button type="submit" form="form-secure-unlock" class="button outline small" style="color: #c0392b; border-color: #c0392b;">
                                @icon('close') Disable Lock
                            </button>
                        @else
                            {{-- LOCK FORM with Input --}}
                            <div class="flex-container-row gap-s items-center">
                                <input type="text" name="custom_password" form="form-secure-lock" placeholder="Custom Password (Optional)" class="input-base small" style="width: 200px; margin:0;" autocomplete="off">
                                <button type="submit" form="form-secure-lock" class="button small" style="background-color: #27ae60; border-color: #27ae60; color: #fff;">
                                    @icon('lock') Enable Lock
                                </button>
                            </div>
                        @endif
                    </div>

                </div>
            </div>
        </div>

        {{-- CARD 2: API CONTROL (Completely Separate Container) --}}
        @if($isProtected)
            <div class="mb-m">
                <div class="card p-m" style="border: 1px solid #444444; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
                    <div class="flex-container-row justify-space-between items-center wrap gap-m">
                        
                        {{-- Left Side: Description --}}
                        <div style="flex: 1;">
                            <div class="flex-container-row items-center gap-xs">
                                <span class="bold">@icon('code') API Access Control</span>
                            </div>
                            <p class="text-muted small mb-none mt-xs">
                                @if($allowApi)
                                    External scripts can read this page <strong>without</strong> a PIN.
                                @else
                                    External scripts must provide the PIN via the <code>X-PIN-Code</code> header.
                                @endif
                            </p>
                        </div>

                        {{-- Right Side: Toggle Button --}}
                        <div>
                            <button type="submit" form="form-secure-api-toggle" class="button small outline" 
                                style="{{ $allowApi ? 'color: #27ae60; border-color: #27ae60;' : 'color: #666; border-color: #444444;' }}">
                                @if($allowApi)
                                    @icon('check-circle') API: Unlocked
                                @else
                                    @icon('cancel') API: Locked
                                @endif
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        @endif

    @endif
    {{-- END PIN CONTROL --}}

    <hr class="mb-m">

    <div class="flex-container-row justify-space-between gap-m wrap">
        <div class="flex min-width-m">
            @if($model instanceof \BookStack\Entities\Models\Bookshelf)
                <p class="small text-muted mb-none">
                    * {{ trans('entities.shelves_permissions_create') }}
                </p>
            @endif
        </div>
        <div class="text-right">
            <a href="{{ $model->getUrl() }}" class="button outline">{{ trans('common.cancel') }}</a>
            <button type="submit" class="button">{{ trans('entities.permissions_save') }}</button>
        </div>
    </div>
</form>

{{-- 
    HIDDEN FORMS
--}}
@if($model instanceof \BookStack\Entities\Models\Page)
    {{-- Form to Lock Page --}}
    <form id="form-secure-lock" action="/secure-lock-page" method="POST" style="display: none;">
        {!! csrf_field() !!}
        <input type="hidden" name="page_id" value="{{ $model->id }}">
        <input type="hidden" name="redirect_to" value="{{ url()->current() }}">
    </form>

    {{-- Form to Unlock Page --}}
    <form id="form-secure-unlock" action="/secure-unlock-page" method="POST" style="display: none;">
        {!! csrf_field() !!}
        <input type="hidden" name="page_id" value="{{ $model->id }}">
        <input type="hidden" name="redirect_to" value="{{ url()->current() }}">
    </form>

    {{-- Form to Toggle API --}}
    <form id="form-secure-api-toggle" action="/secure-toggle-api" method="POST" style="display: none;">
        {!! csrf_field() !!}
        <input type="hidden" name="page_id" value="{{ $model->id }}">
        <input type="hidden" name="redirect_to" value="{{ url()->current() }}">
    </form>
@endif
