/**
 * Build-time edits to the vendored sources.
 *
 * Explicit find/replace pairs rather than forked files: the vendored tree stays identical
 * to upstream and the full delta is visible here. Each patch must match exactly once, or
 * at least once when `all` is set; the loader fails the build otherwise, so an upstream
 * update cannot drop a patch unnoticed.
 *
 * Fields: file, why, find, replace, and optional `all` to replace every occurrence.
 */
module.exports = [
    // -----------------------------------------------------------------------------
    // Theme compatibility: alias Filament class names onto the equivalent components
    // so panel themes apply here unmodified.
    //
    // Limited to elements that carry no surface of their own. Themes use .fi-section,
    // .fi-card, .fi-widget and .fi-panel to make surfaces transparent, assuming panel
    // chrome behind them; those components are this interface's structure, so aliasing
    // those names removes its cards, rows and widgets.
    // -----------------------------------------------------------------------------
    {
        file: 'components/NavigationBar.tsx',
        why: 'Themes style the panel header through .fi-topbar.',
        find: "<div className={'w-full bg-neutral-900 shadow-md overflow-x-auto'}>",
        replace: "<div className={'fi-topbar w-full bg-neutral-900 shadow-md overflow-x-auto'}>",
    },
    {
        file: 'components/elements/SubNavigation.tsx',
        why: 'The tab strip is the counterpart of Filament\'s .fi-sc-tabs.',
        find: 'const SubNavigation = styled.div`',
        replace: "const SubNavigation = styled.div.attrs({ className: 'fi-sc-tabs' })`",
    },
    {
        file: 'components/elements/ContentContainer.tsx',
        why: 'Every page body flows through this, which is what .fi-main and .fi-page address.',
        find: 'const ContentContainer = styled.div`',
        replace: "const ContentContainer = styled.div.attrs({ className: 'fi-main fi-page' })`",
    },
    {
        file: 'components/elements/GreyRowBox.tsx',
        why: 'Row panels are the closest thing here to a Filament section.',
        find: 'export default styled.div<{ $hoverable?: boolean }>`',
        replace: "export default styled.div.attrs({ className: 'fi-section' })<{ $hoverable?: boolean }>`",
    },
    {
        file: 'components/elements/TitledGreyBox.tsx',
        why: 'Titled boxes are the section/card surface themes expect to colour.',
        find: 'className={className}',
        replace: "className={`fi-section fi-card ${className || ''}`}",
    },
    {
        file: 'components/server/console/StatBlock.tsx',
        why: 'The CPU, memory and disk blocks on the console are widgets in Filament terms. Without this they keep their opaque bg-gray-600 and stay grey under every theme.',
        find: "classNames(styles.stat_block, 'bg-gray-600', className)",
        replace: "classNames(styles.stat_block, 'bg-gray-600', 'fi-section fi-widget', className)",
    },
    {
        file: 'components/server/console/ChartBlock.tsx',
        why: 'The console graphs are widgets too.',
        find: "classNames(styles.chart_container, 'group')",
        replace: "classNames(styles.chart_container, 'group', 'fi-section fi-widget')",
    },
    {
        file: 'components/server/files/FileManagerContainer.tsx',
        why: 'The list component needs the same ordering the container applied inline, so the helper is exported rather than duplicated.',
        find: 'const sortFiles = (files: FileObject[]): FileObject[] => {',
        replace: 'export const sortFiles = (files: FileObject[]): FileObject[] => {',
    },
    {
        file: 'components/server/files/FileManagerContainer.tsx',
        why: 'Pulls in the paginated, searchable list that replaces the truncated one below.',
        find: "import style from './style.module.css';",
        replace: "import style from './style.module.css';\nimport FileList from '@ui/files/FileList';",
    },
    {
        file: 'components/server/files/FileManagerContainer.tsx',
        why: 'A directory was cut off at 250 entries with a warning and no way to reach the rest. The list now pages through the whole directory and can be filtered by name.',
        find:
            '{files.length > 250 && (\n' +
            '                                    <div css={tw`rounded bg-yellow-400 mb-px p-3`}>\n' +
            '                                        <p css={tw`text-yellow-900 text-sm text-center`}>\n' +
            '                                            This directory is too large to display in the browser, limiting the output\n' +
            '                                            to the first 250 files.\n' +
            '                                        </p>\n' +
            '                                    </div>\n' +
            '                                )}\n' +
            '                                {sortFiles(files.slice(0, 250)).map((file) => (\n' +
            '                                    <FileObjectRow key={file.key} file={file} />\n' +
            '                                ))}',
        replace: '<FileList files={files} />',
    },
    {
        file: 'components/server/files/FileObjectRow.tsx',
        why: 'File rows carry an opaque bg-neutral-700, so without a row name a theme that clears table rows on the panel left these solid here.',
        find: 'className={styles.file_row}',
        replace: "className={`fi-ta-row ${styles.file_row}`}",
    },
    {
        file: 'components/server/files/FileObjectRow.tsx',
        why: 'The cells inside those rows. Appears twice — the link and non-link variants — and both are cells.',
        all: true,
        find: 'className={styles.details}',
        replace: "className={`fi-ta-cell ${styles.details}`}",
    },
    {
        file: 'components/elements/Icon.tsx',
        why: 'Every icon in the interface renders through this one component, so tagging it is what lets a theme size or colour icons here the way it does on the panel.',
        find: 'className={className}',
        replace: "className={`fi-icon ${className || ''}`}",
    },
    {
        file: 'components/elements/Modal.tsx',
        why: "The dimmed backdrop behind a modal. Filament splits the two, so the mask answers to the overlay names and the window below to .fi-modal-window.",
        find: 'export const ModalMask = styled.div`',
        replace: "export const ModalMask = styled.div.attrs({ className: 'fi-modal fi-modal-close-overlay' })`",
    },
    {
        file: 'components/elements/Modal.tsx',
        why: 'The modal panel itself — the surface a theme colours.',
        find: 'const ModalContainer = styled.div<{ alignTop?: boolean }>`',
        replace: "const ModalContainer = styled.div.attrs({ className: 'fi-modal-window' })<{ alignTop?: boolean }>`",
    },
    {
        file: 'components/elements/dropdown/Dropdown.tsx',
        why: 'The floating menu panel, which themes address as .fi-dropdown-panel. Nothing outside its own directory imports this component today, so it is not in the bundle — the patch is here so it is already covered if an upstream bump wires it up.',
        find: "classNames(styles.items_container, 'w-56')",
        replace: "classNames(styles.items_container, 'w-56', 'fi-dropdown-panel')",
    },
    {
        file: 'components/elements/DropdownMenu.tsx',
        why: 'The file manager still uses this older menu, so it needs the same name as the newer one or a theme would style only half the dropdowns.',
        find: "css={tw`absolute bg-white p-2 rounded border border-neutral-700 shadow-lg text-neutral-500 z-50`}",
        replace: "className={'fi-dropdown-panel'}\n                        css={tw`absolute bg-white p-2 rounded border border-neutral-700 shadow-lg text-neutral-500 z-50`}",
    },

    {
        file: 'assets/css/GlobalStylesheet.ts',
        why: "Body background and text were hardcoded here. styled-components injects at runtime, later in the cascade than any stylesheet in the head, so a theme setting body colours could never apply. The shell supplies the same defaults where a theme can override them.",
        find: '${tw`font-sans bg-neutral-800 text-neutral-200`};',
        replace:
            // Spelled out rather than left to the utility: dropping the colour classes from
            // it also dropped the font stack, leaving the body with the browser default.
            "font-family: 'IBM Plex Sans', ui-sans-serif, system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif;",
    },
    {
        file: 'components/elements/SubNavigation.tsx',
        why: 'Tighten tab spacing uniformly so a long tab row fits. Must stay uniform: making it conditional on tab count with :nth-last-child only matches the leading tabs and leaves the row uneven.',
        find: '${tw`inline-block py-3 px-4 text-neutral-300 no-underline whitespace-nowrap transition-all duration-150`};',
        replace: '${tw`inline-block py-3 px-3 text-neutral-300 no-underline whitespace-nowrap transition-all duration-150`};',
    },
    {
        file: 'components/elements/SubNavigation.tsx',
        why: 'The extra left margin between tabs compounds with the padding; the padding alone spaces them evenly.',
        find: '${tw`ml-2`};',
        replace: '${tw`ml-0`};',
    },
    {
        file: 'components/elements/PageContentBlock.tsx',
        why: "Footer carries the panel's own name rather than the upstream project's.",
        find: 'Pterodactyl&reg;',
        replace: '{window.__pteroUiBrand()}',
    },
    {
        file: 'components/elements/PageContentBlock.tsx',
        why: 'The footer link followed the name off to the upstream project; it points at this panel instead.',
        find: "href={'https://pterodactyl.io'}",
        replace: 'href={window.__pteroUiBrandUrl()}',
    },
    {
        file: 'components/NavigationBar.tsx',
        why: 'Topbar items share padding but not content width (icons ~0.875rem, avatar 1.25rem), so gaps look uneven. Give them a common width and centre the contents.',
        find: '${tw`flex items-center h-full no-underline text-neutral-300 px-6 cursor-pointer transition-all duration-150`};',
        replace:
            '${tw`flex items-center justify-center h-full no-underline text-neutral-300 px-6 cursor-pointer transition-all duration-150`};\n' +
            '        min-width: 4.5rem;',
    },
    {
        file: 'components/server/console/Console.tsx',
        why: 'Terminal font and size were fixed values; they now come from the account preferences.',
        find: 'const terminal = useMemo(() => new Terminal({ ...terminalProps }), []);',
        replace: 'const terminal = useMemo(() => new Terminal({ ...terminalProps, ...window.__pteroUiConsole() }), []);',
    },
    {
        file: 'components/server/console/Console.tsx',
        why: 'Fitting the terminal to its container overwrites the row count on every resize, so the preference is re-applied after each fit.',
        find: 'const fitAddon = new FitAddon();',
        replace: 'const fitAddon = window.__pteroUiFit(new FitAddon(), terminal);',
    },
    {
        file: 'components/server/console/chart.ts',
        why: 'Graph window was a fixed 20 points; it now follows the configured graph period.',
        find: 'labels: Array(20)',
        replace: 'labels: Array(window.__pteroUiGraphPoints())',
    },
    {
        file: 'components/server/console/chart.ts',
        why: 'Second half of the graph window change. Appears twice (seeding and resetting the dataset); both must follow the label count or clearing a graph restores the old fixed length.',
        all: true,
        find: 'data: Array(20).fill(-5)',
        replace: 'data: Array(window.__pteroUiGraphPoints()).fill(-5)',
    },
    {
        file: 'components/server/network/NetworkContainer.tsx',
        why: "A server with no allocations and an allocation limit of 0 rendered nothing at all: the list is empty and the limit block is gated on the limit being above zero, so the page came up blank. It now says so.",
        find: '{data.map((allocation) => (',
        replace:
            '{!data.length && (\n' +
            '                        <p css={tw`text-sm text-neutral-400 text-center`}>No allocations added.</p>\n' +
            '                    )}\n' +
            '                    {data.map((allocation) => (',
    },
    {
        file: 'components/server/console/ServerDetailsBlock.tsx',
        why: 'Show "Not Assigned" rather than "n/a" when a server has no allocation.',
        find: "return !match ? 'n/a' : ",
        replace: "return !match ? 'Not Assigned' : ",
    },
    {
        file: 'components/dashboard/ServerRow.tsx',
        why: 'Show "Not Assigned" when a server has no default allocation; the cell was rendering empty.',
        find: '{server.allocations',
        replace:
            "{!server.allocations.some((alloc) => alloc.isDefault) && 'Not Assigned'}\n                        {server.allocations",
    },
    {
        file: 'components/server/databases/DatabasesContainer.tsx',
        why: 'Spinner was gated on record count, which cannot distinguish an empty collection from an unloaded one, so an empty list blocked on the network every visit.',
        find: '{!databases.length && loading ? (',
        replace: '{!databases.length && loading && window.__pteroUiFirstVisit() ? (',
    },
    {
        file: 'components/server/schedules/ScheduleContainer.tsx',
        why: 'Same record-count gate as the databases list.',
        find: '{!schedules.length && loading ? (',
        replace: '{!schedules.length && loading && window.__pteroUiFirstVisit() ? (',
    },
    {
        file: 'components/server/users/UsersContainer.tsx',
        why: 'Same record-count gate as the databases list.',
        find: 'if (!subusers.length && (loading || !Object.keys(permissions).length)) {',
        replace:
            'if (!subusers.length && (loading || !Object.keys(permissions).length) && window.__pteroUiFirstVisit()) {',
    },
];
