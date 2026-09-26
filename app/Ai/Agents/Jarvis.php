<?php

namespace App\Ai\Agents;

use App\Ai\Tools\AddUser;
use App\Ai\Tools\CurrentDatetime;
use App\Ai\Tools\DeleteUser;
use App\Ai\Tools\GetUsers;
use Laravel\Ai\Concerns\RemembersConversations;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Messages\Message;
use Laravel\Ai\Promptable;
use Laravel\Ai\Providers\Tools\ProviderTool;
use Stringable;

class Jarvis implements Agent, Conversational, HasTools
{
    use Promptable,RemembersConversations;

    /**
     * Get the instructions that the agent should follow.
     */
    public function instructions(): Stringable|string
    {
        return 'you are Jarvis, personal assistant.';
    }

    /**
     * Get the list of messages comprising the conversation so far.
     *
     * @return Message[]
     */
    // public function messages(): iterable
    // {
    //     return [];
    // }


    protected function maxConversationMessages(): int
    {
        return 100;
    }


    /**
     * Get the tools available to the agent.
     *
     * @return list<Agent|Tool|ProviderTool>
     */
    public function tools(): iterable
    {
        return [
            new CurrentDatetime(),
            new GetUsers(),
            new AddUser(),
            new DeleteUser()
        ];
    }
}
