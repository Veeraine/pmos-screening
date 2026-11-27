<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Staff accounts') }}
            </h2>

            <a href="{{ route('staff.create') }}"
               class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
                {{ __('Add staff') }}
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
            @if (session('status'))
                <div class="rounded-md bg-green-50 p-4 text-sm text-green-800">
                    {{ session('status') }}
                </div>
            @endif

            @error('staff')
                <div class="rounded-md bg-red-50 p-4 text-sm text-red-800">
                    {{ $message }}
                </div>
            @enderror

            <div class="bg-white overflow-x-auto shadow-sm sm:rounded-lg">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                        <tr>
                            <th class="px-6 py-3">{{ __('Name') }}</th>
                            <th class="px-6 py-3">{{ __('Email') }}</th>
                            <th class="px-6 py-3">{{ __('Role') }}</th>
                            <th class="px-6 py-3">{{ __('Status') }}</th>
                            <th class="px-6 py-3"><span class="sr-only">{{ __('Actions') }}</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 text-gray-900">
                        @foreach ($staff as $member)
                            <tr @class(['text-gray-400' => ! $member->is_active])>
                                <td class="px-6 py-4 font-medium">{{ $member->name }}</td>
                                <td class="px-6 py-4">{{ $member->email }}</td>
                                <td class="px-6 py-4">{{ $member->role->label() }}</td>
                                <td class="px-6 py-4">
                                    @if ($member->is_active)
                                        <span class="inline-flex rounded-full bg-green-100 px-2 py-1 text-xs font-medium text-green-800">{{ __('Active') }}</span>
                                    @else
                                        <span class="inline-flex rounded-full bg-gray-100 px-2 py-1 text-xs font-medium text-gray-600">{{ __('Deactivated') }}</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-right">
                                    @if ($member->is(auth()->user()))
                                        <span class="text-xs text-gray-500">{{ __('You') }}</span>
                                    @elseif ($member->is_active)
                                        <form method="POST" action="{{ route('staff.activation.destroy', $member) }}"
                                              onsubmit="return confirm(@js(__('Deactivate :name? They will no longer be able to log in.', ['name' => $member->name])))">
                                            @csrf
                                            @method('DELETE')
                                            <x-danger-button>{{ __('Deactivate') }}</x-danger-button>
                                        </form>
                                    @else
                                        <form method="POST" action="{{ route('staff.activation.store', $member) }}">
                                            @csrf
                                            <x-secondary-button type="submit">{{ __('Reactivate') }}</x-secondary-button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{ $staff->links() }}
        </div>
    </div>
</x-app-layout>
