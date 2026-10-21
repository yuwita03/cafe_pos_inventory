import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import test from 'node:test';
import { compileScript, parse } from '@vue/compiler-sfc';
import ts from 'typescript';
import { createRenderer, nextTick, reactive } from 'vue';

const source = await readFile(
    new URL(
        '../resources/js/components/admin/QrisPaymentDialog.vue',
        import.meta.url,
    ),
    'utf8',
);
const { descriptor } = parse(source);
const script = compileScript(descriptor, {
    id: 'qris-test',
    inlineTemplate: true,
});
const code = script.content
    .replace(
        /import AppDialog from [^;]+;/,
        `const AppDialog = { props: ['open'], setup: (props, { slots }) => () => props.open ? h('dialog', [...(slots.default?.() ?? []), ...(slots.footer?.() ?? [])]) : null };`,
    )
    .replace(
        /import BaseButton from [^;]+;/,
        `const BaseButton = { setup: (_, { slots, attrs }) => () => h('button', attrs, slots.default?.()) };`,
    )
    .replace(
        /import \{ formatRupiah \} from [^;]+;/,
        `const formatRupiah = (value) => 'Rp' + value;`,
    )
    .replaceAll(/from ['"]vue['"]/g, `from '${import.meta.resolve('vue')}'`);
const compiled = ts.transpileModule(
    `import { h } from '${import.meta.resolve('vue')}';\n${code}`,
    {
        compilerOptions: {
            module: ts.ModuleKind.ESNext,
            target: ts.ScriptTarget.ES2022,
        },
    },
).outputText;
const { default: QrisPaymentDialog } = await import(
    `data:text/javascript;base64,${Buffer.from(compiled).toString('base64')}`
);

function node(type, text = '') {
    return { type, text, children: [], props: {}, parent: null };
}
const renderer = createRenderer({
    createElement: (type) => node(type),
    createText: (text) => node('text', text),
    createComment: (text) => node('comment', text),
    setText: (target, text) => {
        target.text = text;
    },
    setElementText: (target, text) => {
        target.text = text;
        target.children = [];
    },
    patchProp: (target, key, _, value) => {
        target.props[key] =
            key === 'disabled' ? value === '' || Boolean(value) : value;
    },
    parentNode: (target) => target.parent,
    nextSibling: (target) =>
        target.parent?.children[target.parent.children.indexOf(target) + 1] ??
        null,
    insert: (target, parent, anchor = null) => {
        if (target.parent) {
            target.parent.children.splice(
                target.parent.children.indexOf(target),
                1,
            );
        }
        target.parent = parent;
        parent.children.splice(
            anchor ? parent.children.indexOf(anchor) : parent.children.length,
            0,
            target,
        );
    },
    remove: (target) => {
        target.parent?.children.splice(
            target.parent.children.indexOf(target),
            1,
        );
        target.parent = null;
    },
});
function all(root, type) {
    return [root, ...root.children.flatMap((child) => all(child, type))].filter(
        (child) => child.type === type,
    );
}
function text(root) {
    return root.text + root.children.map(text).join('');
}

test('QR display preserves its countdown across closing, hides expired QR, disables payment controls, and emits close and view-order events', async (t) => {
    let elapsed = 0;
    let tick;
    t.mock.method(performance, 'now', () => elapsed);
    t.mock.method(globalThis, 'setInterval', (callback) => {
        tick = callback;
        return 1;
    });
    t.mock.method(globalThis, 'clearInterval', () => {});
    const props = reactive({
        open: true,
        order: {
            number: 'ORD-000001',
            customerName: 'Alya',
            fulfillment: 'takeaway',
            total: 25000,
            qris: {
                state: 'ready',
                qrUrl: 'https://api.sandbox.midtrans.com/v2/qris/test/qr-code',
                serverTime: '2026-10-21T00:00:00Z',
                expiresAt: '2026-10-21T00:15:00Z',
            },
        },
    });
    let closed = 0;
    let viewed = 0;
    const root = node('root');
    const { h } = await import('vue');
    const mounted = renderer.createApp({
        render: () =>
            h(QrisPaymentDialog, {
                ...props,
                onClose: () => {
                    closed++;
                },
                onViewOrder: () => {
                    viewed++;
                },
            }),
    });
    mounted.mount(root);
    try {
        assert.equal(text(all(root, 'time')[0]), '15:00');
        assert.equal(all(root, 'img').length, 1);
        assert.ok(text(root).includes('ORD-000001'));
        assert.ok(text(root).includes('Rp25000'));
        elapsed = 60000;
        tick();
        await nextTick();
        assert.equal(text(all(root, 'time')[0]), '14:00');
        props.open = false;
        await nextTick();
        elapsed = 120000;
        props.open = true;
        await nextTick();
        assert.equal(text(all(root, 'time')[0]), '13:00');
        for (const label of ['Periksa status', 'Batalkan pembayaran']) {
            const button = all(root, 'button').find(
                (candidate) => text(candidate) === label,
            );
            assert.ok(button, `Missing payment button: ${label}`);
            assert.equal(
                button.props.disabled,
                true,
                `${label} must be disabled`,
            );
        }
        all(root, 'button')
            .find((button) => text(button) === 'Tutup tampilan')
            .props.onClick();
        assert.equal(closed, 1);
        all(root, 'button')
            .find((button) => text(button) === 'Lihat pesanan')
            .props.onClick();
        assert.equal(viewed, 1);
        assert.equal(props.order.qris.state, 'ready');
        elapsed = 900000;
        tick();
        await nextTick();
        assert.equal(text(all(root, 'time')[0]), '00:00');
        assert.equal(all(root, 'img').length, 0);
        assert.ok(text(root).includes('Status pembayaran belum dipastikan'));
    } finally {
        mounted.unmount();
    }
});
