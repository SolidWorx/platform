import { Controller } from '@hotwired/stimulus';
import { Editor } from '@tiptap/core';
import StarterKit from '@tiptap/starter-kit';
import Link from '@tiptap/extension-link';
import Placeholder from '@tiptap/extension-placeholder';

interface CommandDefinition {
    run: (editor: Editor) => void;
    active: (editor: Editor) => boolean;
}

/**
 * Behaviour for the platform TextEditorType.
 *
 * Mounts a Tiptap editor over the (now hidden) textarea, wires the server-rendered toolbar buttons to
 * editor commands, and keeps the textarea value in sync so the form submits HTML or JSON. All output is
 * re-sanitized server-side, so this controller only handles presentation and convenience.
 */
/* stimulusFetch: 'lazy' */
export default class extends Controller<HTMLElement> {
    static targets = ['input', 'editor', 'toolbar'];

    static values = {
        outputFormat: { type: String, default: 'html' },
        placeholder: { type: String, default: '' },
        height: { type: String, default: '' },
    };

    declare readonly inputTarget: HTMLTextAreaElement;
    declare readonly editorTarget: HTMLElement;
    declare readonly hasToolbarTarget: boolean;
    declare readonly toolbarTarget: HTMLElement;
    declare readonly outputFormatValue: string;
    declare readonly placeholderValue: string;
    declare readonly heightValue: string;

    private editor: Editor | null = null;

    private readonly commands: Record<string, CommandDefinition> = {
        bold: { run: (e) => e.chain().focus().toggleBold().run(), active: (e) => e.isActive('bold') },
        italic: { run: (e) => e.chain().focus().toggleItalic().run(), active: (e) => e.isActive('italic') },
        strike: { run: (e) => e.chain().focus().toggleStrike().run(), active: (e) => e.isActive('strike') },
        heading1: { run: (e) => e.chain().focus().toggleHeading({ level: 1 }).run(), active: (e) => e.isActive('heading', { level: 1 }) },
        heading2: { run: (e) => e.chain().focus().toggleHeading({ level: 2 }).run(), active: (e) => e.isActive('heading', { level: 2 }) },
        heading3: { run: (e) => e.chain().focus().toggleHeading({ level: 3 }).run(), active: (e) => e.isActive('heading', { level: 3 }) },
        bulletList: { run: (e) => e.chain().focus().toggleBulletList().run(), active: (e) => e.isActive('bulletList') },
        orderedList: { run: (e) => e.chain().focus().toggleOrderedList().run(), active: (e) => e.isActive('orderedList') },
        blockquote: { run: (e) => e.chain().focus().toggleBlockquote().run(), active: (e) => e.isActive('blockquote') },
        code: { run: (e) => e.chain().focus().toggleCode().run(), active: (e) => e.isActive('code') },
        codeBlock: { run: (e) => e.chain().focus().toggleCodeBlock().run(), active: (e) => e.isActive('codeBlock') },
        horizontalRule: { run: (e) => e.chain().focus().setHorizontalRule().run(), active: () => false },
        link: { run: (e) => this.toggleLink(e), active: (e) => e.isActive('link') },
        undo: { run: (e) => e.chain().focus().undo().run(), active: () => false },
        redo: { run: (e) => e.chain().focus().redo().run(), active: () => false },
    };

    connect(): void {
        const isJson = this.outputFormatValue === 'json';

        this.editor = new Editor({
            element: this.editorTarget,
            extensions: [
                // StarterKit (v2) bundles formatting, lists, blockquote, code, history, etc. but not Link.
                StarterKit,
                Link.configure({ openOnClick: false, autolink: true }),
                Placeholder.configure({ placeholder: this.placeholderValue }),
            ],
            content: this.initialContent(isJson),
            onUpdate: () => this.sync(),
            onTransaction: () => this.refreshToolbar(),
        });

        if (this.heightValue !== '' && this.editor !== null) {
            this.editor.view.dom.style.minHeight = this.heightValue;
        }

        // The textarea is now driven by the editor; hide it and let the server enforce "required".
        this.inputTarget.classList.add('d-none');
        this.inputTarget.required = false;
        this.inputTarget.setAttribute('aria-hidden', 'true');
        this.inputTarget.tabIndex = -1;

        this.refreshToolbar();
    }

    disconnect(): void {
        this.editor?.destroy();
        this.editor = null;
    }

    run(event: Event): void {
        const button = (event.currentTarget as HTMLElement).closest<HTMLElement>('[data-editor-command]');
        const command = button?.dataset.editorCommand;

        if (this.editor === null || command === undefined || this.commands[command] === undefined) {
            return;
        }

        this.commands[command].run(this.editor);
    }

    private initialContent(isJson: boolean): string | Record<string, unknown> {
        const raw = this.inputTarget.value.trim();

        if (raw === '') {
            return '';
        }

        if (!isJson) {
            return raw;
        }

        try {
            return JSON.parse(raw) as Record<string, unknown>;
        } catch {
            return '';
        }
    }

    private sync(): void {
        if (this.editor === null) {
            return;
        }

        if (this.outputFormatValue === 'json') {
            this.inputTarget.value = this.editor.isEmpty ? '' : JSON.stringify(this.editor.getJSON());
        } else {
            this.inputTarget.value = this.editor.isEmpty ? '' : this.editor.getHTML();
        }

        this.inputTarget.dispatchEvent(new Event('input', { bubbles: true }));
    }

    private refreshToolbar(): void {
        if (this.editor === null || !this.hasToolbarTarget) {
            return;
        }

        const editor = this.editor;

        this.toolbarTarget.querySelectorAll<HTMLElement>('[data-editor-command]').forEach((button) => {
            const command = button.dataset.editorCommand;

            if (command !== undefined && this.commands[command] !== undefined) {
                button.classList.toggle('active', this.commands[command].active(editor));
            }
        });
    }

    private toggleLink(editor: Editor): void {
        const previous = (editor.getAttributes('link').href as string | undefined) ?? '';
        const url = window.prompt('Enter a URL (leave empty to remove the link)', previous);

        if (url === null) {
            return;
        }

        if (url === '') {
            editor.chain().focus().extendMarkRange('link').unsetLink().run();

            return;
        }

        editor.chain().focus().extendMarkRange('link').setLink({ href: url }).run();
    }
}
