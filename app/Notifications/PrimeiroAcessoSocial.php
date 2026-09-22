<?php

namespace App\Notifications;

use App\Support\Formatos;
use App\Support\ProvedorSocial;
use Filament\Facades\Filament;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * "Sua conta foi acessada pelo Google pela primeira vez."
 *
 * Vai para o e-mail da conta quando um provedor aparece pela PRIMEIRA vez numa conta que já
 * existia — no modo padrão, em que a entrada acontece (ADR-01 e ADR-03 da wiki
 * `vinculo-de-provedor-social`). É detecção: se não foi a pessoa, ela troca (ou define) a senha
 * e avisa quem administra. Nada automático além do aviso.
 *
 * `ShouldQueue` como `ConviteDeAcesso`: sem worker de fila nada sai — `composer dev` sobe um.
 */
class PrimeiroAcessoSocial extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly ProvedorSocial $provedor,
        public readonly string $ip,
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $rotulo = $this->provedor->rotulo();
        $app    = (string) config('app.name');

        return (new MailMessage)
            ->subject("Sua conta no {$app} foi acessada pelo {$rotulo} pela primeira vez")
            ->greeting(__('Hello!'))
            ->line(__('Someone just signed in to your account on :app using a :provider account with the same e-mail — and it was the first time this path was used.', ['app' => $app, 'provider' => $rotulo]))
            ->line(__('When: :at · IP: :ip', ['at' => now()->format(Formatos::dataHora()), 'ip' => $this->ip]))
            ->line(__('If that was you, there is nothing to do: from now on that :provider account signs in directly.', ['provider' => $rotulo]))
            ->line(__('If that was NOT you, change your password now (or set one, using the "Set password by e-mail" block on your profile) and tell whoever administers the system.'))
            ->action('Abrir o painel', Filament::getPanel('app')->getUrl())
            ->salutation('Atenciosamente, '.$app);
    }
}
