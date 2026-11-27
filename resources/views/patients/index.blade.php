<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Patients') }}
            </h2>

            @if (auth()->user()->hasRole(App\Role::Nurse))
                <a href="{{ route('patients.create') }}"
                   class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
                    {{ __('Register patient') }}
                </a>
            @endif
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
            <form method="GET" action="{{ route('patients.index') }}" class="flex gap-2">
                <x-text-input name="search" type="search" class="block w-full" :value="$search"
                              :placeholder="__('Search by patient number, name or phone')" autofocus />
                <x-primary-button>{{ __('Search') }}</x-primary-button>
            </form>

            <div class="bg-white overflow-x-auto shadow-sm sm:rounded-lg">
                @if ($patients->isEmpty())
                    <p class="p-6 text-sm text-gray-600">
                        @if ($search !== '')
                            {{ __('No patients match ":search".', ['search' => $search]) }}
                        @else
                            {{ __('No patients have been registered yet.') }}
                        @endif
                    </p>
                @else
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                            <tr>
                                <th class="px-6 py-3">{{ __('Patient no.') }}</th>
                                <th class="px-6 py-3">{{ __('Name') }}</th>
                                <th class="px-6 py-3">{{ __('Date of birth') }}</th>
                                <th class="px-6 py-3">{{ __('Phone') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 text-gray-900">
                            @foreach ($patients as $patient)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 font-mono">
                                        <a href="{{ route('patients.show', $patient->id) }}" class="text-indigo-600 hover:underline">{{ $patient->patientNumber }}</a>
                                    </td>
                                    <td class="px-6 py-4 font-medium">
                                        <a href="{{ route('patients.show', $patient->id) }}" class="hover:underline">{{ $patient->fullName() }}</a>
                                    </td>
                                    <td class="px-6 py-4">{{ $patient->dateOfBirth->format('d M Y') }} ({{ $patient->age() }})</td>
                                    <td class="px-6 py-4">{{ $patient->phone }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>

            {{ $patients->links() }}
        </div>
    </div>
</x-app-layout>
