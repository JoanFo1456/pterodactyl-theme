import React, { useMemo, useState } from 'react';
import tw from 'twin.macro';
import FileObjectRow from '@/components/server/files/FileObjectRow';
import PaginationFooter from '@/components/elements/table/PaginationFooter';
import Input from '@/components/elements/Input';
import { FileObject } from '@/api/server/files/loadDirectory';
import { sortFiles } from '@/components/server/files/FileManagerContainer';

/**
 * The file list: paginated, and filterable by name.
 *
 * Upstream renders the first 250 entries of a directory and warns that the rest are not
 * shown, which leaves everything past that point unreachable — in a directory of a thousand
 * files, seven hundred and fifty of them simply had no route to them in the interface.
 *
 * The 250 is kept, as a page size rather than a ceiling: it is about what the browser draws
 * comfortably in one go, and paging is what the panel's own tables do. The filter is the
 * faster way through a large directory, so it narrows the whole listing rather than the
 * current page — searching from page 1 finds a file that lives on page 4.
 */
const PER_PAGE = 250;

const FileList = ({ files }: { files: FileObject[] }) => {
    const [search, setSearch] = useState('');
    const [page, setPage] = useState(1);

    const sorted = useMemo(() => sortFiles(files), [files]);

    const matched = useMemo(() => {
        const term = search.trim().toLowerCase();

        return term ? sorted.filter((file) => file.name.toLowerCase().includes(term)) : sorted;
    }, [sorted, search]);

    const totalPages = Math.max(1, Math.ceil(matched.length / PER_PAGE));

    // Filtering can shrink the list out from under the current page, so the page in use is
    // clamped rather than stored back — clearing the search returns you where you were.
    const current = Math.min(page, totalPages);
    const visible = matched.slice((current - 1) * PER_PAGE, current * PER_PAGE);

    return (
        <>
            {sorted.length > 0 && (
                <Input
                    type={'text'}
                    value={search}
                    placeholder={'Search this directory…'}
                    aria-label={'Search this directory'}
                    css={tw`mb-4`}
                    onChange={(event) => {
                        setSearch(event.currentTarget.value);
                        setPage(1);
                    }}
                />
            )}

            <div className={'fi-ta-ctn fi-ta-table fi-ta-body'}>
                {visible.map((file) => (
                    <FileObjectRow key={file.key} file={file} />
                ))}
            </div>

            {!matched.length && search.trim().length > 0 && (
                <p css={tw`text-sm text-neutral-400 text-center py-4`}>
                    Nothing in this directory matches “{search.trim()}”.
                </p>
            )}

            <PaginationFooter
                pagination={{
                    total: matched.length,
                    count: visible.length,
                    perPage: PER_PAGE,
                    currentPage: current,
                    totalPages,
                }}
                onPageSelect={setPage}
            />
        </>
    );
};

export default FileList;
