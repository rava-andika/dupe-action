import DataTable, { createWidget, Permission } from "@/Components/DataVisual/DataTable";
import FileUpload from "@/Components/Form/FileUpload";
import KeyValue from "@/Components/Form/KeyValue";
import KeyValueTable from "@/Components/Form/KeyValueTable";
import SelectField from "@/Components/Form/SelectField";
import TextField from "@/Components/Form/TextField";
import PreloadableLink from "@/Components/PreloadableLink";
import MainLayout from "@/Layouts/MainLayout";
import { router } from "@inertiajs/react";
import { ExternalLink } from "lucide-react";
import { ReactElement, useMemo, useState } from "react";

const status = {
    pending: "Pending",
    approved: "Approved",
    rejected: "Rejected",
};

function Registrations({
    locale,
    supportedLocales,
    statusMenu,
}: {
    locale: string;
    supportedLocales: AppPageProps["supportedLocales"];
    statusMenu: string;
}) {
    const [loading, setLoading] = useState(false);
    const [selectedStatus, setSelectedStatus] = useState(null);
    const handleChangeStatusMenu = (status: string) => {
        setLoading(true);

        router.get(
            window.location.pathname,
            {
                ...Object.fromEntries(
                    new URLSearchParams(window.location.search)
                ),
                status,
            },
            {
                preserveState: true,
                preserveScroll: true,
                onFinish: () => setLoading(false),
            }
        );
    };
    
    const excludeActions = useMemo(() => {
        const actions = ["delete", "create"];

        if (statusMenu !== "pending") {
            actions.push("update");
        }

        return actions;
    }, [statusMenu]);

    return (
        <DataTable
            title="Registrations"
            parentLoading={loading}
            excludeActions={excludeActions as Permission[]}
            tableColumnsShow={[
                "team_name",
                "competition_name",
                "submitted_at",
                "reviewer_name",
            ]}
            formatTableColumns={{
                submitted_at: (value) =>
                    new Date(value || new Date()).toLocaleString(),
                reviewer_name: (value) => (value ? value : "-"),
            }}
            formatColumnsPopup={{
                team: createWidget(KeyValueTable, {
                    renderAction: (key: string) => (
                        <PreloadableLink
                            href={`/${locale}/admin/teams/${key}`}
                            className="flex items-center gap-1 cursor-pointer hover:underline"
                        >
                            <ExternalLink size={16} />
                            <span>View Team</span>
                        </PreloadableLink>
                    ),
                }),
                competition: createWidget(KeyValueTable, {
                    renderAction: (key: string) => (
                        <PreloadableLink
                            href={`/${locale}/admin/competitions/${key}`}
                            className="flex items-center gap-1 cursor-pointer hover:underline"
                        >
                            <ExternalLink size={16} />
                            <span>View Competition</span>
                        </PreloadableLink>
                    ),
                }),
                reviewed_by: createWidget(KeyValueTable, {
                    renderAction: (key: string) => (
                        <PreloadableLink
                            href={`/${locale}/admin/users/${key}`}
                            className="flex items-center gap-1 cursor-pointer hover:underline"
                        >
                            <ExternalLink size={16} />
                            <span>View Admin</span>
                        </PreloadableLink>
                    ),
                }),
                payment_proof: createWidget(FileUpload, {
                    disabled: true,
                    fullWidth: true,
                }),
                submitted_at: createWidget(TextField, {
                    type: "datetime",
                    formatter: (value, onChange) =>
                        onChange
                    ? new Date(value).toISOString()
                    : new Date(value || new Date()).toLocaleString(),
                }),
                status: createWidget(SelectField, {
                    title: "Status",
                    placeholder: "Select Status",
                    options: status,
                    formatter(value) {
                        setSelectedStatus(value);
                        return value;
                    },
                }),
                notes: createWidget(KeyValue, {
                    required: selectedStatus === "rejected",
                    keys: supportedLocales.active,
                    itemSchema: {
                        key: {
                            disabled: true,
                        },
                        value: {
                            label: "Notes",
                            type: "textarea",
                        },
                    },
                }),
                group_link: createWidget(TextField, {
                    title: "Group Link",
                    placeholder: "Group Link",
                    required: selectedStatus === "approved",
                })
            }}
        >
            <div className="max-w-full flex justify-center">
                <div className="flex gap-4 p-3 bg-white dark:bg-gray-800 rounded-xl">
                    {Object.entries(status).map(([key, value]) =>
                        <div
                            key={key}
                            onClick={() => handleChangeStatusMenu(key)}
                            className={`flex items-center gap-2 px-3 py-2 ${
                                statusMenu === key &&
                                "text-yellow-700 dark:text-yellow-500 bg-gray-100 dark:bg-gray-600"
                            } hover:bg-gray-300 dark:hover:bg-gray-600 cursor-pointer rounded-lg font-semibold capitalize`}
                        >
                            <span>{value}</span>
                        </div>
                    )}
                </div>
            </div>
        </DataTable>
    );
}

Registrations.layout = (page: ReactElement) => {
    return <MainLayout title="Registrations | Admin">{page}</MainLayout>;
};

export default Registrations;
