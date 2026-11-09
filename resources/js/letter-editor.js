function plainHtml(value) {
    const node = document.createElement('div');
    node.textContent = value || '';
    return node.innerHTML.split(/\r?\n\r?\n/).map(p => `<p>${p.replace(/\r?\n/g, '<br>')}</p>`).join('');
}

export function letterEditor(value, format, sample = '') {
    // Keep ProseMirror outside Alpine's reactive proxy.
    let editor;
    let unwatch;
    let disposed = false;
    let dialogScroll = 0;
    return {
        value, format, sample,
        revision: 0,
        words: 0,
        characters: 0,
        empty: true,
        expanded: false,
        alignment: 'left',
        loading: true,
        loadError: false,
        async init() {
            let Editor, StarterKit, TextAlign;
            try {
                const modules = await Promise.all([
                    import('@tiptap/core'), import('@tiptap/starter-kit'), import('@tiptap/extension-text-align'),
                ]);
                Editor = modules[0].Editor;
                StarterKit = modules[1].default;
                TextAlign = modules[2].default;
            } catch (error) {
                this.loading = false;
                this.loadError = true;
                console.error('Editor surat gagal dimuat.', error);
                return;
            }
            if (disposed) return;
            this.loading = false;
            const initial = this.format === 'html' ? this.value : plainHtml(this.value);
            const refresh = () => {
                this.revision++;
                this.empty = editor.isEmpty;
                const text = editor.getText();
                this.words = text.trim() ? text.trim().split(/\s+/u).length : 0;
                this.characters = [...text].length;
                this.alignment = ['left', 'center', 'right', 'justify'].find(textAlign => editor.isActive({ textAlign })) || 'left';
            };
            editor = new Editor({
                element: this.$refs.canvas,
                extensions: [
                    StarterKit.configure({ heading: false, blockquote: false, code: false, codeBlock: false, horizontalRule: false, link: false, strike: false }),
                    TextAlign.configure({ types: ['paragraph'] }),
                ],
                content: initial || '<p></p>',
                editorProps: {
                    attributes: {
                        role: 'textbox', 'aria-label': 'Isi Surat', 'aria-multiline': 'true',
                        'data-placeholder': 'Tulis isi surat di sini, atau gunakan kerangka awal di bawah.',
                        class: 'letter-editor-canvas letter-body', spellcheck: 'true', lang: 'id',
                    },
                },
                onUpdate: () => {
                    this.value = editor.isEmpty ? '' : editor.getHTML();
                    this.format = 'html';
                    refresh();
                },
                onSelectionUpdate: refresh,
                onTransaction: () => this.revision++,
            });
            this.format = 'html';
            this.value = editor.isEmpty ? '' : editor.getHTML();
            refresh();
            this.$watch('expanded', expanded => {
                const dialog = this.$el.closest('dialog');
                if (!dialog) return;
                if (expanded) {
                    dialogScroll = dialog.scrollTop;
                    this.$nextTick(() => dialog.scrollTo(0, 0));
                } else {
                    this.$nextTick(() => dialog.scrollTo(0, dialogScroll));
                }
            });
            unwatch = this.$watch('value', next => {
                const content = this.format === 'html' ? next : plainHtml(next);
                if ((editor.isEmpty ? '' : editor.getHTML()) !== (content || '')) {
                    editor.commands.setContent(content || '<p></p>', { emitUpdate: false });
                    refresh();
                }
            });
        },
        active(name) {
            void this.revision;
            return editor?.isActive(name) || false;
        },
        can(name) {
            void this.revision;
            if (!editor) return false;
            if (name === 'indent') return editor.can().sinkListItem('listItem');
            if (name === 'outdent') return editor.can().liftListItem('listItem');
            return editor.can()[name]();
        },
        run(name) {
            if (!editor) return;
            const chain = editor.chain().focus();
            const actions = {
                bold: () => chain.toggleBold().run(),
                italic: () => chain.toggleItalic().run(),
                underline: () => chain.toggleUnderline().run(),
                orderedList: () => chain.toggleOrderedList().run(),
                bulletList: () => chain.toggleBulletList().run(),
                indent: () => chain.sinkListItem('listItem').run(),
                outdent: () => chain.liftListItem('listItem').run(),
                undo: () => chain.undo().run(),
                redo: () => chain.redo().run(),
                clear: () => chain.unsetAllMarks().clearNodes().unsetTextAlign().run(),
            };
            actions[name]?.();
        },
        align(value) { editor?.chain().focus().setTextAlign(value).run(); },
        useSample() {
            if (editor?.isEmpty && this.sample) editor.chain().focus().setContent(this.sample).run();
        },
        destroy() {
            disposed = true;
            unwatch?.();
            editor?.destroy();
        },
    };
}
