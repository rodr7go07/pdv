<x-app-layout>
    <x-slot name="titulo">Mi cuenta</x-slot>

    <div class="grid gap-6 lg:grid-cols-2">
        <section class="rounded-xl border border-anil-100 bg-white p-5 shadow-[0_2px_0_0_var(--color-anil-100)] sm:p-6">
            <livewire:profile.update-profile-information-form />
        </section>

        <section class="rounded-xl border border-anil-100 bg-white p-5 shadow-[0_2px_0_0_var(--color-anil-100)] sm:p-6">
            <livewire:profile.update-password-form />
        </section>
    </div>
</x-app-layout>
