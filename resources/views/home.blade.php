<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Lista zakupów
            </h2>

            <a href="{{ route('products.create') }}"
                class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
                Dodaj produkt
            </a>
        </div>
    </x-slot>

    <div class="py-8 sm:py-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                @if ($products->isEmpty())
                    <div class="p-6 text-gray-900">
                        Lista zakupów jest pusta. Nikt nie dodał jeszcze żadnego produktu.
                    </div>
                @else
                    <ul class="divide-y divide-gray-200">
                        @foreach ($products as $product)
                            <li class="p-4 sm:px-6 flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1">
                                <span class="text-gray-900 font-medium break-words">{{ $product->name }}</span>
                                <span class="text-sm text-gray-500">{{ $product->category->name }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
