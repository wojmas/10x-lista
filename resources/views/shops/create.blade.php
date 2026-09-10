<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Dodaj sklep
        </h2>
    </x-slot>

    <div class="py-8 sm:py-12">
        <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <form method="POST" action="{{ route('shops.store') }}" class="p-6">
                    @csrf

                    <div>
                        <x-input-label for="name" value="Nazwa sklepu" />
                        <x-text-input id="name" class="block mt-1 w-full" type="text" name="name"
                            :value="old('name')" required autofocus />
                        <x-input-error :messages="$errors->get('name')" class="mt-2" />
                    </div>

                    <div class="mt-6">
                        <x-input-label value="Kategorie, które ten sklep oferuje" />

                        <div class="mt-2 space-y-1">
                            @foreach ($categories as $category)
                                <label for="category_{{ $category->id }}"
                                    class="flex items-center gap-3 py-2 cursor-pointer">
                                    <input id="category_{{ $category->id }}" type="checkbox" name="category_ids[]"
                                        value="{{ $category->id }}"
                                        @checked(in_array($category->id, old('category_ids', [])))
                                        class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                                    <span class="text-gray-700 break-words">{{ $category->name }}</span>
                                </label>
                            @endforeach
                        </div>

                        {{-- A bad id is keyed as "category_ids.0", which get('category_ids')
                             does not match, so both keys have to be read or the form comes
                             back with no error shown at all. --}}
                        @php
                            $categoryErrors = array_merge(
                                $errors->get('category_ids'),
                                Arr::flatten($errors->get('category_ids.*')),
                            );
                        @endphp
                        <x-input-error :messages="$categoryErrors" class="mt-2" />
                    </div>

                    <div class="mt-4">
                        <x-input-label for="new_category" value="…albo dopisz kategorię, której nie ma na liście" />
                        <x-text-input id="new_category" class="block mt-1 w-full" type="text" name="new_category"
                            :value="old('new_category')" />
                        <x-input-error :messages="$errors->get('new_category')" class="mt-2" />
                        <p class="mt-1 text-sm text-gray-500">
                            Pole opcjonalne. Sklep musi mieć co najmniej jedną kategorię — zaznaczoną albo wpisaną tutaj.
                        </p>
                    </div>

                    <div class="flex flex-wrap items-center justify-end gap-4 mt-6">
                        <a href="{{ route('shops.index') }}" class="text-sm text-gray-600 underline hover:text-gray-900">
                            Anuluj
                        </a>
                        <x-primary-button>Dodaj sklep</x-primary-button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
