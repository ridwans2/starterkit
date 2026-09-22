{{--
    Bloco do perfil que manda o link de definição de senha por e-mail. Vive logo acima do
    bloco "Senha" do Breezy porque é a alternativa a ele: quem entrou por login social não tem
    senha atual para digitar. Ver o docblock de App\Livewire\DefinirSenhaPorEmail.
--}}
<x-filament::section
    :aside="true"
    :heading="__('Set password by e-mail')"
    :description="__('No password yet, or you do not remember the current one — anyone who signed in through social login, for example. We send a link to your e-mail; opening it lets you set a new password. With one you can change it here, turn on two-factor authentication, and unlock the session.')"
>
    <form wire:submit.prevent="enviar" class="space-y-6">
        <p class="text-sm text-gray-600 dark:text-gray-400">
            {{ __('Requesting the link ends your session, because the page that sets the password only opens for people who are signed out.') }}
        </p>

        <div class="text-right">
            <x-filament::button type="submit" color="gray" wire:loading.attr="disabled">
                {{ __('Receive link by e-mail') }}
            </x-filament::button>
        </div>
    </form>
</x-filament::section>
