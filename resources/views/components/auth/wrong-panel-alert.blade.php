@if (is_array(session('wrong_panel')))
    <x-ui.alert variant="warning" title="Wrong login panel" class="animate-fade-up [animation-delay:400ms]">
        <p>{{ session('wrong_panel.message') }}</p>
    </x-ui.alert>
@endif
