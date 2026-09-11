<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight break-words">
            Edytuj sklep: {{ $shop->name }}
        </h2>
    </x-slot>

    <div class="py-8 sm:py-12">
        <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                @include('shops.partials.form', [
                    'action' => route('shops.update', $shop),
                    'method' => 'PUT',
                    'submitLabel' => 'Zapisz zmiany',
                    'shop' => $shop,
                ])
            </div>
        </div>
    </div>
</x-app-layout>
