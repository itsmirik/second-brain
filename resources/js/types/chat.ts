export type ChatRole = 'user' | 'assistant';

export type ChatTurn = {
    role: ChatRole;
    content: string;
};
