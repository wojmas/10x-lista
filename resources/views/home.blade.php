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
            {{--
                Rekomendacja stoi nad listą, bo to ona odpowiada na pytanie „gdzie
                jechać" — lista mówi tylko „co kupić". Odmiana po liczbie nie jest
                tu potrzebna: „z N kategorii" ma tę samą formę dopełniacza dla
                jednej i dla wielu.
            --}}
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6 p-6">
                @if ($recommendation->total === 0)
                    <p class="text-gray-900">
                        Dodaj produkty, żeby zobaczyć rekomendowany sklep.
                    </p>
                @elseif ($recommendation->shop === null)
                    <p class="text-gray-900">
                        Żaden sklep nie pokrywa kategorii z tej listy.
                    </p>
                    <p class="mt-1 text-sm text-gray-500">
                        Sprawdź kategorie przypisane sklepom w
                        <a href="{{ route('shops.index') }}"
                            class="text-indigo-600 underline hover:text-indigo-500">konfiguracji sklepów</a>.
                    </p>
                @else
                    <p class="text-sm text-gray-500">Jedź do</p>
                    <p class="mt-1 text-2xl font-semibold text-gray-900 break-words">
                        {{ $recommendation->shop->name }}
                    </p>
                    <p class="mt-1 text-sm text-gray-500">
                        Pokrywa {{ $recommendation->covered }} z {{ $recommendation->total }} kategorii z listy.
                    </p>

                    @if ($recommendation->alternative !== null)
                        <p class="mt-4 text-sm text-gray-500 break-words">
                            Alternatywa: <span class="font-medium text-gray-900">{{ $recommendation->alternative->name }}</span>
                            — {{ $recommendation->alternativeCovered }} z {{ $recommendation->total }} kategorii.
                        </p>
                    @endif
                @endif
            </div>

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

                                {{--
                                    Napis nazywa naraz intencję („kupione") i skutek
                                    („usuń"), bo potwierdzenia nie ma — produkt znika
                                    od razu i bezpowrotnie (§Non-Goals PRD: brak
                                    historii zakupów). Tekst zamiast ikony kosza daje
                                    też duży cel dotykowy i czyta się w czytniku
                                    ekranu bez aria-label.
                                --}}
                                <form method="POST" action="{{ route('products.destroy', $product) }}">
                                    @csrf
                                    @method('DELETE')

                                    {{--
                                        cursor-pointer jest tu jawnie, bo Tailwind 4
                                        zmienił domyślny kursor przycisku na `default`
                                        — bez tej klasy najechanie nie zmienia wskaźnika.
                                    --}}
                                    <button type="submit"
                                        class="cursor-pointer inline-flex items-center px-3 py-1.5 rounded-md bg-red-600 text-xs font-semibold text-white uppercase tracking-widest hover:bg-red-500 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 transition ease-in-out duration-150">
                                        Kupione — usuń
                                    </button>
                                </form>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
