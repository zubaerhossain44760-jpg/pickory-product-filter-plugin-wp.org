const { registerBlockType } = wp.blocks;
const { useBlockProps } = wp.blockEditor;
const { createElement } = wp.element;

registerBlockType('pickory/product-filter', {
    title: 'Product Filter',
    icon: 'filter',
    category: 'woocommerce',
    edit: () => {
        const blockProps = useBlockProps();
        return createElement(
            'div',
            blockProps,
            createElement(
                'p',
                { style: { padding: '20px', background: '#f7f7f7', textAlign: 'center', border: '1px dashed #ccc' } },
                '🔍 Pickory Product Filter (renders on frontend)'
            )
        );
    },
    save: () => null, // Server-side rendered
});
