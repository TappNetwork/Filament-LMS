@props(['category'])

<span style="background-color: {{ $category->hexColor() }}1a; color: {{ $category->hexColor() }}; backdrop-filter: blur(4px); -webkit-backdrop-filter: blur(4px);"
      {{ $attributes->merge(['class' => 'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium']) }}>
    {{ $slot }}
</span>
