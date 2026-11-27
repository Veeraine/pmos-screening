<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Register patient') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                <form method="POST" action="{{ route('patients.store') }}" class="max-w-xl space-y-6">
                    @csrf

                    @error('duplicate')
                        <div class="rounded-md bg-amber-50 p-4 text-sm text-amber-900 space-y-3">
                            <p>{{ $message }}</p>
                            <p>
                                <a href="{{ route('patients.index', ['search' => old('first_name').' '.old('last_name')]) }}"
                                   class="font-medium underline">{{ __('Find the existing patient') }}</a>
                            </p>
                        </div>
                    @enderror

                    @if ($errors->has('duplicate') || old('confirmed_different_person'))
                        <label class="flex items-start gap-2 text-sm text-gray-700">
                            <input type="hidden" name="confirmed_different_person" value="0">
                            <input type="checkbox" name="confirmed_different_person" value="1" @checked(old('confirmed_different_person'))
                                   class="mt-0.5 rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                            <span>{{ __('This is a different person who has the same name and date of birth.') }}</span>
                        </label>
                    @endif

                    <div class="grid gap-6 sm:grid-cols-2">
                        <div>
                            <x-input-label for="first_name" :value="__('First name')" />
                            <x-text-input id="first_name" name="first_name" type="text" class="mt-1 block w-full" :value="old('first_name')" required autofocus />
                            <x-input-error :messages="$errors->get('first_name')" class="mt-2" />
                        </div>

                        <div>
                            <x-input-label for="last_name" :value="__('Surname')" />
                            <x-text-input id="last_name" name="last_name" type="text" class="mt-1 block w-full" :value="old('last_name')" required />
                            <x-input-error :messages="$errors->get('last_name')" class="mt-2" />
                        </div>
                    </div>

                    <div>
                        <x-input-label for="date_of_birth" :value="__('Date of birth')" />
                        <x-text-input id="date_of_birth" name="date_of_birth" type="date" class="mt-1 block w-full" :value="old('date_of_birth')" :max="now()->toDateString()" required />
                        <x-input-error :messages="$errors->get('date_of_birth')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="phone" :value="__('Phone (optional)')" />
                        <x-text-input id="phone" name="phone" type="tel" class="mt-1 block w-full" :value="old('phone')" />
                        <x-input-error :messages="$errors->get('phone')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="address" :value="__('Address (optional)')" />
                        <x-text-input id="address" name="address" type="text" class="mt-1 block w-full" :value="old('address')" />
                        <x-input-error :messages="$errors->get('address')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="previous_diagnoses" :value="__('Previous diagnoses (optional)')" />
                        <textarea id="previous_diagnoses" name="previous_diagnoses" rows="3"
                                  class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">{{ old('previous_diagnoses') }}</textarea>
                        <p class="mt-1 text-sm text-gray-600">{{ __('Any conditions diagnosed before this patient was registered here.') }}</p>
                        <x-input-error :messages="$errors->get('previous_diagnoses')" class="mt-2" />
                    </div>

                    <div class="flex items-center gap-4">
                        <x-primary-button>{{ __('Register patient') }}</x-primary-button>
                        <a href="{{ route('patients.index') }}" class="text-sm text-gray-600 underline hover:text-gray-900">{{ __('Cancel') }}</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
