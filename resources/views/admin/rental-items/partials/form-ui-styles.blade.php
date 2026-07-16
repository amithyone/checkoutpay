<style>
    .rental-admin-form input[type="text"],
    .rental-admin-form input[type="number"],
    .rental-admin-form input[type="url"],
    .rental-admin-form input[type="date"],
    .rental-admin-form input[type="file"],
    .rental-admin-form input[type="search"],
    .rental-admin-form select,
    .rental-admin-form textarea {
        border: 1px solid #111827 !important;
        border-radius: 0.375rem;
        padding: 0.375rem 0.625rem;
        width: 100%;
        background: #fff;
        font-size: 0.875rem;
        line-height: 1.25rem;
    }

    .rental-admin-form input:focus,
    .rental-admin-form select:focus,
    .rental-admin-form textarea:focus {
        outline: none;
        border-color: #3C50E0 !important;
        box-shadow: 0 0 0 2px rgba(60, 80, 224, 0.2);
    }

    .rental-admin-save-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.375rem;
        width: 100%;
        padding: 0.625rem 1rem;
        border-radius: 0.375rem;
        background: #4f46e5;
        color: #fff;
        font-size: 0.875rem;
        font-weight: 600;
        box-shadow: 0 1px 2px rgba(0, 0, 0, 0.08);
    }

    .rental-admin-save-btn:hover {
        background: #4338ca;
    }

    .rental-admin-save-bar {
        position: sticky;
        bottom: 0;
        z-index: 30;
        margin-top: 1rem;
        padding: 0.75rem 0;
        background: linear-gradient(to top, #fff 85%, rgba(255, 255, 255, 0));
    }
</style>
