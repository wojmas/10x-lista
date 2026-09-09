<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Dodaj produkt
        </h2>
    </x-slot>

    <div class="py-8 sm:py-12">
        <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <form method="POST" action="{{ route('products.store') }}" class="p-6">
                    @csrf

                    <div>
                        <x-input-label for="name" value="Nazwa produktu" />
                        <x-text-input id="name" class="block mt-1 w-full" type="text" name="name"
                            :value="old('name')" required autofocus />
                        <x-input-error :messages="$errors->get('name')" class="mt-2" />
                    </div>

                    <div class="mt-6">
                        <x-input-label for="category_id" value="Kategoria" />
                        <select id="category_id" name="category_id"
                            class="block mt-1 w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                            <option value="">— wybierz z listy —</option>
                            @foreach ($categories as $category)
                                <option value="{{ $category->id }}" @selected(old('category_id') == $category->id)>
                                    {{ $category->name }}
                                </option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('category_id')" class="mt-2" />
                    </div>

                    <div class="mt-4">
                        <x-input-label for="new_category" value="…albo wpisz nową kategorię" />
                        <x-text-input id="new_category" class="block mt-1 w-full" type="text" name="new_category"
                            :value="old('new_category')" />
                        <x-input-error :messages="$errors->get('new_category')" class="mt-2" />
                        <p class="mt-1 text-sm text-gray-500">
                            Wypełnij jedno z dwóch pól — wybierz kategorię z listy albo wpisz nową.
                        </p>
                    </div>

                    <div class="flex flex-wrap items-center justify-end gap-4 mt-6">
                        <a href="{{ route('home') }}" class="text-sm text-gray-600 underline hover:text-gray-900">
                            Anuluj
                        </a>
                        <x-primary-button>Dodaj do listy</x-primary-button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
