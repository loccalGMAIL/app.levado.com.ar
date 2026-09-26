@props(['locations', 'customers', 'id'])

{{--
    Select único de destino (Sucursales / Clientes agrupados), envía
    "location:{id}" / "customer:{id}" en un solo campo `name="destination"` —
    el mismo molde de <optgroup> que purchases/match.blade.php. TomSelect lo
    envuelve vía [data-searchable] (init global en app.js, dropdownParent:
    'body' para no recortarse dentro del modal). El x-data que envuelve este
    partial debe traer `destination` (string) más los getters
    destinationType/destinationId que derivan de él — ver los tres modales
    que lo incluyen.
--}}
<select id="{{ $id }}" name="destination" data-searchable x-model="destination"
    class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm focus:border-horno focus:ring-horno">
    <option value="">Elegí un destino…</option>
    @if($locations->isNotEmpty())
        <optgroup label="Sucursales">
            @foreach($locations as $location)
                <option value="location:{{ $location->id }}">{{ $location->name }}</option>
            @endforeach
        </optgroup>
    @endif
    @if($customers->isNotEmpty())
        <optgroup label="Clientes">
            @foreach($customers as $customer)
                <option value="customer:{{ $customer->id }}">{{ $customer->name }}</option>
            @endforeach
        </optgroup>
    @endif
</select>
