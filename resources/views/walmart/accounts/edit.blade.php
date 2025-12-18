@extends('walmart.layout')

@section('content')
    <div class="card">
        <h2>{{ isset($account) ? 'Edit' : 'Create' }} Walmart Account</h2>

        <form method="POST" action="{{ isset($account) ? route('accounts.update', $account) : route('accounts.store') }}">
            @csrf
            @if(isset($account)) @method('PUT') @endif

            <div>
                <label>Name</label><br/>
                <input name="name" value="{{ old('name', $account->name ?? '') }}" style="width:420px">
            </div><br/>

            <div>
                <label>Market</label><br/>
                <input name="market" value="{{ old('market', $account->market ?? 'US') }}" style="width:120px">
            </div><br/>

            <div>
                <label>Client ID</label><br/>
                <input name="client_id" value="{{ old('client_id', $account->client_id ?? '') }}" style="width:420px">
            </div><br/>

            <div>
                <label>Client Secret</label><br/>
                <input name="client_secret" value="{{ old('client_secret', $account->client_secret ?? '') }}" style="width:420px">
            </div><br/>

            <div>
                <label><input type="checkbox" name="is_active" value="1" {{ old('is_active', $account->is_active ?? true) ? 'checked' : '' }}> Active</label>
            </div><br/>

            <button class="btn">Save</button>
        </form>
    </div>
@endsection
