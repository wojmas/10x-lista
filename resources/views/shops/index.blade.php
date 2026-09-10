<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Sklepy
            </h2>
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
                                <span class="text-gray-900 font-medium break-words">{{ $shop->name }}</span>

                                <div class="mt-2 flex flex-wrap gap-1.5">
                                    @foreach ($shop->categories as $category)
                                        <span class="inline-flex items-center px-2 py-0.5 rounded bg-gray-100 text-sm text-gray-600 break-words">
                                            {{ $category->name }}
                                        </span>
                                    @endforeach
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
