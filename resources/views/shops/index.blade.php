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

                                    <a href="{{ route('shops.edit', $shop) }}"
                                        class="text-sm text-gray-600 underline hover:text-gray-900">
                                        Edytuj
                                    </a>
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
