<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Sklepy
            </h2>

            <a href="{{ route('shops.create') }}"
                class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
                Dodaj sklep
            </a>
        </div>
    </x-slot>

    <div class="py-8 sm:py-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                @if ($shops->isEmpty())
                    <div class="p-6 text-gray-900">
                        Nie ma jeszcze żadnego sklepu. Dodaj pierwszy, żeby rekomendacja miała z czego wybierać.
                    </div>
                @else
                    <ul class="divide-y divide-gray-200">
                        @foreach ($shops as $shop)
                            <li class="p-4 sm:px-6">
                                <div class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-2">
                                    <span class="text-gray-900 font-medium break-words">{{ $shop->name }}</span>

                                    <div class="flex flex-wrap items-center gap-4">
                                        {{--
                                            aria-label, bo sam napis powtarza się w
                                            każdym wierszu: czytnik ekranu przeglądający
                                            listę akcji odczytałby „Edytuj, Edytuj,
                                            Edytuj" bez wskazania sklepu (WCAG 2.4.4).
                                            Widoczny tekst zostaje krótki.
                                        --}}
                                        <a href="{{ route('shops.edit', $shop) }}"
                                            aria-label="Edytuj sklep {{ $shop->name }}"
                                            class="text-sm text-gray-600 underline hover:text-gray-900">
                                            Edytuj
                                        </a>

                                        {{--
                                            Potwierdzenie jest tu, a nie przy produktach,
                                            bo sklep to konfiguracja z przypisanymi
                                            kategoriami — pomyłkowy klik kasuje pracę,
                                            której nie odtwarza samo wpisanie nazwy.
                                            Przy wyłączonym JS formularz nadal wysyła się
                                            poprawnie, tylko bez pytania.

                                            Nazwa sklepu idzie w komunikacie przez @js, a nie
                                            przez zwykłe echo: apostrof w nazwie zamknąłby
                                            łańcuch JS w atrybucie onsubmit.
                                        --}}
                                        <form method="POST" action="{{ route('shops.destroy', $shop) }}"
                                            onsubmit="return confirm('Usunąć sklep ' + @js($shop->name) + '? Przypisane mu kategorie przepadną, a rekomendacja przeliczy się bez niego.')">
                                            @csrf
                                            @method('DELETE')

                                            {{--
                                                cursor-pointer jest tu jawnie, bo Tailwind 4
                                                zmienił domyślny kursor przycisku na `default`.
                                            --}}
                                            <button type="submit"
                                                aria-label="Usuń sklep {{ $shop->name }}"
                                                class="cursor-pointer inline-flex items-center px-3 py-1.5 rounded-md bg-red-600 text-xs font-semibold text-white uppercase tracking-widest hover:bg-red-500 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 transition ease-in-out duration-150">
                                                Usuń
                                            </button>
                                        </form>
                                    </div>
                                </div>

                                {{--
                                    Sklep bez kategorii powstaje dopiero przy edycji
                                    i jest stanem dozwolonym. Bez tego zdania wiersz
                                    wygląda na uszkodzony, a nic nie mówi, dlaczego
                                    ten sklep nigdy nie pojawia się w rekomendacji.
                                --}}
                                @if ($shop->categories->isEmpty())
                                    <p class="mt-2 text-sm text-gray-500">
                                        Brak kategorii — ten sklep nie trafi do rekomendacji.
                                    </p>
                                @else
                                    <div class="mt-2 flex flex-wrap gap-1.5">
                                        @foreach ($shop->categories as $category)
                                            <span class="inline-flex items-center px-2 py-0.5 rounded bg-gray-100 text-sm text-gray-600 break-words">
                                                {{ $category->name }}
                                            </span>
                                        @endforeach
                                    </div>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
