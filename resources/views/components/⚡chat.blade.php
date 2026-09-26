<?php

use Illuminate\Support\Str;
use Livewire\Attributes\Validate;
use Livewire\Component;
use App\Ai\Agents\Jarvis;
use Livewire\Attributes\Computed;

new class extends Component {
    /** @var array<int, array{role: string, content: string}> */
    public array $messages = [];

    #[Validate('required|string|max:10000')]
    public string $prompt = '';

    public ?string $conversation_id = null;

    public function send(): void
    {
        $this->validate();

        $this->messages[] = ['role' => 'user', 'content' => trim($this->prompt)];
        $this->prompt = '';

        // A resposta é pedida num segundo request para a mensagem do utilizador aparecer já.
        $this->js('$wire.respond()');
    }

    public function respond(): void
    {
        $last = end($this->messages);

        if (!$last || $last['role'] !== 'user') {
            return;
        }

        $this->messages[] = ['role' => 'assistant', 'content' => $this->reply($last['content'])];
    }

    public function newChat(): void
    {
        $this->reset('messages', 'prompt');
    }

    /**
     * Substituir pela chamada ao modelo de IA.
     */
    protected function reply(string $prompt): string
    {
        $user = Auth::user();
        // $response = (new Jarvis)->forUser($user)->prompt($prompt);
        $response = new Jarvis()->continueOrStart($this->conversation_id, as: $user)->prompt($prompt);

        $this->conversation_id = $response->conversationId;

        return (string) $response;
    }

    #[Computed()]
    public function conversations()
    {
        return Auth::user()->conversations()->latest('updated_at')->get();
    }
};
?>

<div class="flex h-[calc(100dvh-3.5rem)] flex-col lg:h-dvh">
    {{-- Barra superior --}}
    <div class="flex h-14 shrink-0 items-center justify-between px-4">
        <span class="text-lg font-medium text-zinc-800 dark:text-zinc-100">Jarvis</span>

        @if ($messages)
            <flux:tooltip :content="__('Nova conversa')" position="bottom">
                <flux:button wire:click="newChat" variant="ghost" size="sm" icon="pencil-square"
                    :aria-label="__('Nova conversa')" />
            </flux:tooltip>
        @endif
    </div>

    {{-- Conversa --}}
    <div x-data x-init="const scroll = () => $el.scrollTo({ top: $el.scrollHeight, behavior: 'smooth' });
    new MutationObserver(scroll).observe($el, { childList: true, subtree: true });" @class(['overflow-y-auto', 'flex-1' => $messages])>
        @if ($messages)
            <div class="mx-auto flex w-full max-w-3xl flex-col gap-8 px-4 pt-4 pb-10">
                @foreach ($messages as $message)
                    @if ($message['role'] === 'user')
                        <div class="flex justify-end" wire:key="msg-{{ $loop->index }}">
                            <div
                                class="max-w-[80%] rounded-3xl bg-zinc-100 px-5 py-2.5 whitespace-pre-wrap text-zinc-900 dark:bg-zinc-700 dark:text-zinc-100">
                                {{ $message['content'] }}</div>
                        </div>
                    @else
                        <div class="group flex flex-col gap-2" wire:key="msg-{{ $loop->index }}">
                            <div class="chat-markdown text-zinc-800 dark:text-zinc-100">
                                {!! Str::markdown($message['content'], ['html_input' => 'strip', 'allow_unsafe_links' => false]) !!}
                            </div>

                            <div x-data="{ copied: false }"
                                class="flex gap-1 opacity-100 transition-opacity lg:opacity-0 lg:group-hover:opacity-100 lg:focus-within:opacity-100">
                                <flux:tooltip :content="__('Copiar')">
                                    <flux:button variant="subtle" size="xs" :aria-label="__('Copiar')"
                                        data-text="{{ $message['content'] }}"
                                        x-on:click="navigator.clipboard.writeText($el.dataset.text); copied = true; setTimeout(() => copied = false, 1500)">
                                        <flux:icon.clipboard x-show="!copied" variant="micro" />
                                        <flux:icon.check x-show="copied" x-cloak variant="micro" />
                                    </flux:button>
                                </flux:tooltip>
                            </div>
                        </div>
                    @endif
                @endforeach

                <div wire:loading.flex wire:target="respond" class="items-center gap-1.5 py-2"
                    aria-label="{{ __('A escrever…') }}">
                    <span class="size-2 animate-pulse rounded-full bg-zinc-400 dark:bg-zinc-400"></span>
                    <span
                        class="size-2 animate-pulse rounded-full bg-zinc-400 [animation-delay:150ms] dark:bg-zinc-400"></span>
                    <span
                        class="size-2 animate-pulse rounded-full bg-zinc-400 [animation-delay:300ms] dark:bg-zinc-400"></span>
                </div>
            </div>
        @endif
    </div>

    {{-- Compositor --}}
    <div @class([
        'shrink-0 px-4',
        'flex flex-1 flex-col justify-center pb-[12vh]' => !$messages,
    ])>
        <div class="mx-auto w-full max-w-3xl">
            @unless ($messages)
                <h1 class="mb-8 text-center text-3xl font-medium text-zinc-800 dark:text-zinc-100">
                    {{ __('Em que posso ajudar?') }}
                </h1>
            @endunless

            <form wire:submit="send" x-data="{
                resize() {
                    $refs.input.style.height = 'auto';
                    $refs.input.style.height = Math.min($refs.input.scrollHeight, 240) + 'px'
                },
            }"
                x-on:submit="$nextTick(() => { $refs.input.style.height = 'auto' })"
                class="flex items-end gap-2 rounded-[28px] border border-zinc-200 bg-white p-2.5 shadow-sm focus-within:border-zinc-300 dark:border-transparent dark:bg-zinc-700 dark:focus-within:border-transparent">
                <flux:tooltip :content="__('Anexar ficheiros')">
                    <flux:button type="button" variant="ghost" icon="plus" class="rounded-full!"
                        :aria-label="__('Anexar ficheiros')" />
                </flux:tooltip>

                <label for="chat-prompt" class="sr-only">{{ __('Mensagem para o Jarvis') }}</label>
                <textarea id="chat-prompt" x-ref="input" wire:model="prompt" x-init="$el.focus()" x-on:input="resize()"
                    x-on:keydown.enter="if (! $event.shiftKey && ! $event.isComposing) { $event.preventDefault(); $el.form.requestSubmit() }"
                    rows="1" placeholder="{{ __('Pergunta alguma coisa') }}"
                    class="max-h-60 min-h-10 flex-1 resize-none bg-transparent py-2 text-base text-zinc-900 outline-none placeholder:text-zinc-400 dark:text-zinc-100"></textarea>

                <button type="submit" x-bind:disabled="!$wire.prompt.trim()" wire:loading.attr="disabled"
                    class="flex size-10 shrink-0 items-center justify-center rounded-full bg-zinc-900 text-white transition hover:bg-zinc-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-zinc-400 disabled:cursor-not-allowed disabled:opacity-30 dark:bg-white dark:text-zinc-900 dark:hover:bg-zinc-200"
                    aria-label="{{ __('Enviar mensagem') }}">
                    <flux:icon.arrow-up variant="mini" />
                </button>
            </form>

            @error('prompt')
                <p class="mt-2 text-center text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
            @enderror

            <p class="py-2.5 text-center text-xs text-zinc-500 dark:text-zinc-400">
                {{ __('O Jarvis pode cometer erros. Confirma informação importante.') }}
            </p>
        </div>
    </div>
    @foreach ($this->conversations as $conversation)
        <button>{{ $conversation->title }}</button>
    @endforeach
</div>
