<?php

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\User;
use Flux\Flux;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Utilizadores')] class extends Component {
    use PasswordValidationRules, ProfileValidationRules, WithPagination;

    private const SORTABLE = ['name', 'email', 'created_at'];

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(except: 'created_at')]
    public string $sortBy = 'created_at';

    #[Url(except: 'desc')]
    public string $sortDirection = 'desc';

    #[Locked]
    public ?int $editingId = null;

    #[Locked]
    public ?int $deletingId = null;

    public string $name = '';

    public string $email = '';

    public string $password = '';

    public string $password_confirmation = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function sort(string $column): void
    {
        if (! in_array($column, self::SORTABLE, true)) {
            return;
        }

        if ($this->sortBy === $column) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $column;
            $this->sortDirection = 'asc';
        }
    }

    /**
     * @return LengthAwarePaginator<int, User>
     */
    #[Computed]
    public function users(): LengthAwarePaginator
    {
        return User::query()
            ->when($this->search, fn ($query) => $query->where(fn ($query) => $query
                ->where('name', 'like', "%{$this->search}%")
                ->orWhere('email', 'like', "%{$this->search}%")))
            ->orderBy(
                in_array($this->sortBy, self::SORTABLE, true) ? $this->sortBy : 'created_at',
                $this->sortDirection === 'asc' ? 'asc' : 'desc',
            )
            ->paginate(10);
    }

    public function create(): void
    {
        $this->resetForm();

        Flux::modal('user-form')->show();
    }

    public function edit(int $id): void
    {
        $user = User::findOrFail($id);

        $this->resetForm();
        $this->editingId = $user->id;
        $this->name = $user->name;
        $this->email = $user->email;

        Flux::modal('user-form')->show();
    }

    public function save(): void
    {
        $validated = $this->validate([
            ...$this->profileRules($this->editingId),
            'password' => $this->editingId
                ? ['nullable', 'string', Password::default(), 'confirmed']
                : $this->passwordRules(),
        ]);

        if (blank($validated['password'])) {
            unset($validated['password']);
        }

        if ($this->editingId) {
            $user = User::findOrFail($this->editingId);
            $user->fill($validated);

            if ($user->isDirty('email')) {
                $user->email_verified_at = null;
            }

            $user->save();

            Flux::toast(variant: 'success', text: __('Utilizador atualizado.'));
        } else {
            User::create($validated);

            Flux::toast(variant: 'success', text: __('Utilizador criado.'));
        }

        Flux::modal('user-form')->close();
        $this->resetForm();
    }

    public function confirmDelete(int $id): void
    {
        $this->deletingId = $id;

        Flux::modal('user-delete')->show();
    }

    public function delete(): void
    {
        abort_if($this->deletingId === Auth::id(), 403);

        User::findOrFail($this->deletingId)->delete();

        $this->deletingId = null;
        Flux::modal('user-delete')->close();
        Flux::toast(variant: 'success', text: __('Utilizador eliminado.'));
    }

    #[Computed]
    public function deleting(): ?User
    {
        return $this->deletingId ? User::find($this->deletingId) : null;
    }

    public function resetForm(): void
    {
        $this->reset('editingId', 'name', 'email', 'password', 'password_confirmation');
        $this->resetValidation();
    }
}; ?>

<div class="mx-auto w-full max-w-5xl">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-3xl font-medium text-zinc-800 dark:text-zinc-100">{{ __('Utilizadores') }}</h1>
            <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                {{ trans_choice(':count pessoa tem acesso ao Jarvis.|:count pessoas têm acesso ao Jarvis.', $this->users->total(), ['count' => $this->users->total()]) }}
            </p>
        </div>

        <button
            type="button"
            wire:click="create"
            class="inline-flex h-10 items-center gap-2 rounded-full bg-zinc-900 ps-3.5 pe-4 text-sm font-medium text-white transition hover:bg-zinc-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-zinc-400 dark:bg-white dark:text-zinc-900 dark:hover:bg-zinc-200"
        >
            <flux:icon.plus variant="mini" />
            {{ __('Novo utilizador') }}
        </button>
    </div>

    <div class="mt-8 flex items-center gap-2 rounded-full border border-zinc-200 bg-white px-4 shadow-sm focus-within:border-zinc-300 dark:border-transparent dark:bg-zinc-700">
        <flux:icon.magnifying-glass variant="mini" class="text-zinc-400" />
        <label for="user-search" class="sr-only">{{ __('Pesquisar utilizadores') }}</label>
        <input
            id="user-search"
            type="search"
            wire:model.live.debounce.300ms="search"
            placeholder="{{ __('Pesquisar por nome ou email') }}"
            class="h-11 flex-1 bg-transparent text-base text-zinc-900 outline-none placeholder:text-zinc-400 dark:text-zinc-100"
        />
        <flux:icon.loading wire:loading wire:target="search" variant="mini" class="text-zinc-400" />
    </div>

    @if ($this->users->isEmpty())
        <div class="flex flex-col items-center py-24 text-center">
            @if ($search)
                <p class="text-zinc-800 dark:text-zinc-100">{{ __('Nenhum utilizador corresponde a ":search".', ['search' => $search]) }}</p>
                <flux:button wire:click="$set('search', '')" variant="ghost" size="sm" class="mt-3">{{ __('Limpar pesquisa') }}</flux:button>
            @else
                <p class="text-zinc-800 dark:text-zinc-100">{{ __('Ainda não há utilizadores.') }}</p>
                <flux:button wire:click="create" variant="ghost" size="sm" class="mt-3">{{ __('Criar o primeiro') }}</flux:button>
            @endif
        </div>
    @else
        <flux:table :paginate="$this->users" class="mt-6">
            <flux:table.columns>
                <flux:table.column sortable :sorted="$sortBy === 'name'" :direction="$sortDirection" wire:click="sort('name')">{{ __('Nome') }}</flux:table.column>
                <flux:table.column class="max-md:hidden" sortable :sorted="$sortBy === 'email'" :direction="$sortDirection" wire:click="sort('email')">{{ __('Email') }}</flux:table.column>
                <flux:table.column class="max-sm:hidden">{{ __('Estado') }}</flux:table.column>
                <flux:table.column class="max-lg:hidden" sortable :sorted="$sortBy === 'created_at'" :direction="$sortDirection" wire:click="sort('created_at')">{{ __('Criado em') }}</flux:table.column>
                <flux:table.column><span class="sr-only">{{ __('Ações') }}</span></flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @foreach ($this->users as $user)
                    <flux:table.row :key="$user->id">
                        <flux:table.cell>
                            <div class="flex items-center gap-3">
                                <flux:avatar size="sm" circle :name="$user->name" :initials="$user->initials()" />
                                <div class="min-w-0">
                                    <div class="truncate font-medium text-zinc-800 dark:text-zinc-100">
                                        {{ $user->name }}
                                        @if ($user->id === auth()->id())
                                            <span class="font-normal text-zinc-500 dark:text-zinc-400">({{ __('tu') }})</span>
                                        @endif
                                    </div>
                                    <div class="truncate text-sm text-zinc-500 md:hidden dark:text-zinc-400">{{ $user->email }}</div>
                                </div>
                            </div>
                        </flux:table.cell>

                        <flux:table.cell class="max-md:hidden">{{ $user->email }}</flux:table.cell>

                        <flux:table.cell class="max-sm:hidden">
                            @if ($user->email_verified_at)
                                <flux:badge size="sm" color="green" inset="top bottom">{{ __('Verificado') }}</flux:badge>
                            @else
                                <flux:badge size="sm" color="zinc" inset="top bottom">{{ __('Por verificar') }}</flux:badge>
                            @endif
                        </flux:table.cell>

                        <flux:table.cell class="max-lg:hidden">
                            <time datetime="{{ $user->created_at?->toIso8601String() }}">{{ $user->created_at?->translatedFormat('d M Y') }}</time>
                        </flux:table.cell>

                        <flux:table.cell align="end">
                            <flux:dropdown position="bottom" align="end">
                                <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" inset="top bottom" :aria-label="__('Ações para :name', ['name' => $user->name])" />

                                <flux:menu>
                                    <flux:menu.item icon="pencil-square" wire:click="edit({{ $user->id }})">{{ __('Editar') }}</flux:menu.item>

                                    @unless ($user->id === auth()->id())
                                        <flux:menu.separator />
                                        <flux:menu.item icon="trash" variant="danger" wire:click="confirmDelete({{ $user->id }})">{{ __('Eliminar') }}</flux:menu.item>
                                    @endunless
                                </flux:menu>
                            </flux:dropdown>
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    @endif

    <flux:modal name="user-form" class="w-full max-w-md" @close="resetForm">
        <form wire:submit="save" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ $editingId ? __('Editar utilizador') : __('Novo utilizador') }}</flux:heading>
                <flux:text class="mt-1">
                    {{ $editingId ? __('Se mudares o email, a pessoa tem de o verificar outra vez.') : __('A pessoa entra com este email e palavra-passe.') }}
                </flux:text>
            </div>

            <flux:input wire:model="name" :label="__('Nome')" required autocomplete="off" />
            <flux:input wire:model="email" :label="__('Email')" type="email" required autocomplete="off" />
            <flux:input
                wire:model="password"
                :label="__('Palavra-passe')"
                :description="$editingId ? __('Deixa em branco para manter a atual.') : null"
                type="password"
                :required="! $editingId"
                autocomplete="new-password"
                viewable
            />
            <flux:input wire:model="password_confirmation" :label="__('Confirmar palavra-passe')" type="password" :required="! $editingId" autocomplete="new-password" />

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancelar') }}</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary">{{ $editingId ? __('Guardar alterações') : __('Criar utilizador') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal name="user-delete" class="w-full max-w-md">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Eliminar :name?', ['name' => $this->deleting?->name]) }}</flux:heading>
                <flux:text class="mt-1">{{ __('A conta de :email é apagada e deixa de conseguir entrar. Não é possível anular.', ['email' => $this->deleting?->email]) }}</flux:text>
            </div>

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancelar') }}</flux:button>
                </flux:modal.close>
                <flux:button wire:click="delete" variant="danger">{{ __('Eliminar utilizador') }}</flux:button>
            </div>
        </div>
    </flux:modal>
</div>