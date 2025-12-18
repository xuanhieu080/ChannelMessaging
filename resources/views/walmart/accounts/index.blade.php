@extends('walmart.layout')

@section('content')
    <div class="card">
        <h2>Walmart Accounts</h2>
        <a class="btn" href="{{ route('accounts.create') }}">+ New account</a>
    </div>

    <div class="card">
        <table>
            <thead>
            <tr>
                <th>ID</th>
                <th>Name</th>
                <th>Market</th>
                <th>Active</th>
                <th>Token</th>
                <th>Actions</th>
            </tr>
            </thead>
            <tbody>
            @foreach($accounts as $a)
                <tr>
                    <td>{{ $a->id }}</td>
                    <td>{{ $a->name }}</td>
                    <td>{{ $a->market }}</td>
                    <td>{{ $a->is_active ? 'YES' : 'NO' }}</td>
                    <td class="muted">
                        {{ $a->token_expires_at ? 'exp: '.$a->token_expires_at->format('Y-m-d H:i') : '—' }}
                    </td>
                    <td>
                        <a class="btn" href="{{ route('accounts.edit', $a) }}">Edit</a>
                        <a class="btn" href="{{ route('walmart.accounts.sync', $a) }}">Sync</a>
                        <form action="{{ route('accounts.destroy', $a) }}" method="POST" style="display:inline">
                            @csrf @method('DELETE')
                            <button class="btn" onclick="return confirm('Delete?')">Delete</button>
                        </form>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>

        <div style="margin-top:10px">{{ $accounts->links() }}</div>
    </div>
@endsection
