const patches = require('./upstream-patches');

/**
 * Applies the documented upstream patches. Runs before babel, on source text.
 */
module.exports = function patchLoader(source) {
    const resource = this.resourcePath.replace(/\\/g, '/');

    let patched = source;

    // Apply every patch matching this file; a file may have more than one.
    for (const patch of patches) {
        if (!resource.endsWith(patch.file)) {
            continue;
        }

        const occurrences = patched.split(patch.find).length - 1;
        const expected = patch.all ? occurrences >= 1 : occurrences === 1;

        if (!expected) {
            // Fail the build rather than skip: an unapplied patch ships as a regression.
            this.emitError(
                new Error(
                    `Upstream patch for ${patch.file} matched ${occurrences} times, expected ` +
                        `${patch.all ? 'at least 1' : 'exactly 1'}. ` +
                        `(${patch.why}) The vendored source has changed — update ` +
                        'integration/upstream-patches.js.',
                ),
            );

            continue;
        }

        // Replacing every occurrence is opt-in; more than one match otherwise means the
        // anchor is too loose and is treated as an error.
        patched = patch.all ? patched.split(patch.find).join(patch.replace) : patched.replace(patch.find, patch.replace);
    }

    return patched;
};
