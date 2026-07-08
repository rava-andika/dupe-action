import DataTable, {
    createWidget,
} from "@/Components/DataVisual/DataTable";
import FileUpload from "@/Components/Form/FileUpload";
import KeyValueTable from "@/Components/Form/KeyValueTable";
import TextField from "@/Components/Form/TextField";
import PreloadableLink from "@/Components/PreloadableLink";
import MainLayout from "@/Layouts/MainLayout";
import { router, useForm } from "@inertiajs/react";
import { AnimatePresence, motion } from "framer-motion";
import {
    Download,
    Edit,
    ExternalLink,
    LoaderCircle,
} from "lucide-react";
import { ReactElement, useEffect, useRef, useState } from "react";
import { useDownload } from "@/Components/DownloadContext";

const status = {
    pending: "Pending",
    reviewed: "Reviewed",
};

function Submissions({
    locale,
    statusMenu,
    competitionMenu,
    competitions,
}: {
    locale: string;
    statusMenu: string;
    competitionMenu: string;
    competitions: string[];
}) {
    const [loading, setLoading] = useState(false);
    const { startDownload } = useDownload();

    const [isPopupOpen, setIsPopupOpen] = useState(false);
    const popupRef = useRef<HTMLFormElement>(null);
    const {
        data,
        setData,
        post,
        processing,
        errors,
        clearErrors,
        isDirty,
        reset,
    } = useForm<{
        ids: number[];
        feedback: any;
    }>({
        ids: [],
        feedback: "",
    });

    const handleClosePopup = (posted = false) => {
        if (
            !posted &&
            isDirty &&
            !confirm("Are you sure you want to discard changes?")
        )
            return;
        clearErrors();
        setIsPopupOpen(false);
        reset();
    };

    const handleChangeMenu = (status: string, competition_name: string) => {
        setLoading(true);

        router.get(
            window.location.pathname,
            {
                ...Object.fromEntries(
                    new URLSearchParams(window.location.search)
                ),
                status,
                competition_name,
            },
            {
                preserveState: true,
                preserveScroll: true,
                onFinish: () => setLoading(false),
            }
        );
    };

    useEffect(() => {
        const handleClickOutside = (e: MouseEvent) => {
            if (e.target instanceof Node) {
                if (!popupRef.current?.contains(e.target)) handleClosePopup();
            }
        };
        const handleBeforeUnload = (event: BeforeUnloadEvent) => {
            event.preventDefault();
            event.returnValue = "";
        };

        if (isPopupOpen) {
            document.addEventListener("mousedown", handleClickOutside);
        }

        if (isDirty) {
            window.addEventListener("beforeunload", handleBeforeUnload);
        }

        return () => {
            document.removeEventListener("mousedown", handleClickOutside);
            window.removeEventListener("beforeunload", handleBeforeUnload);
        };
    }, [isDirty, isPopupOpen]);

    const submit = (e: React.FormEvent<HTMLFormElement>) => {
        e.preventDefault();
        clearErrors();
        const currentPathname = window.location.pathname;
        post(`${currentPathname}/bulk-edit`, {
            preserveScroll: true,
            onSuccess: () => {
                handleClosePopup(true);
            },
        });
    };

    const downloadSelected = async (selectedIds: number[]) => {
        const currentPathname = window.location.pathname;
        await startDownload(selectedIds, `${currentPathname}/bulk-download`);
    };

    return (
        <>
            <AnimatePresence mode="wait">
                {isPopupOpen && (
                    <motion.div
                        initial={{ opacity: 0 }}
                        animate={{ opacity: 1 }}
                        exit={{ opacity: 0 }}
                        transition={{ duration: 0.3 }}
                        className="overflow-y-auto inset-0 fixed bg-black/50 z-100 flex items-start justify-center"
                    >
                        <motion.form
                            ref={popupRef}
                            onSubmit={submit}
                            initial={{ opacity: 0, scaleY: 0 }}
                            animate={{ opacity: 1, scaleY: 1 }}
                            exit={{ opacity: 0, scaleY: 0 }}
                            transition={{ duration: 0.4, ease: "easeOut" }}
                            className="my-auto min-w-full md:min-w-2/3 px-4 py-8 bg-white dark:bg-gray-900 border-1 border-black/30 dark:border-white/30 rounded-lg"
                        >
                            <h2 className="text-2xl font-semibold mb-4 text-center">
                                Bulk Feedback
                            </h2>

                            <FileUpload
                                title="Feedback"
                                name="feedback"
                                value={data.feedback}
                                onChange={(
                                    value:
                                        | null
                                        | string
                                        | File
                                        | (string | File)[]
                                ) => setData("feedback", value)}
                                errors={errors}
                                maxSize={102400}
                                maxFiles={10}
                                multipleFiles
                            />

                            <div className="flex justify-end gap-2 pt-4">
                                <button
                                    type="button"
                                    onClick={() => handleClosePopup()}
                                    className="cursor-pointer px-4 py-2 text-sm font-medium bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-gray-100 rounded-md hover:bg-gray-300 dark:hover:bg-gray-600"
                                >
                                    Cancel
                                </button>

                                <button
                                    type="submit"
                                    disabled={processing}
                                    className="cursor-pointer px-4 py-2 text-sm font-medium bg-yellow-500 text-white rounded-md hover:bg-yellow-600"
                                >
                                    {processing ? (
                                        <div className="flex gap-3 justify-center">
                                            Processing
                                            <LoaderCircle className="animate-spin" />
                                        </div>
                                    ) : (
                                        "Submit"
                                    )}
                                </button>
                            </div>
                        </motion.form>
                    </motion.div>
                )}
            </AnimatePresence>

            <DataTable
                title="Submissions"
                parentLoading={loading}
                excludeActions={["create", "delete"]}
                renderBulkAction={(selectedIds, setIsMenuOpen) => (
                    <>
                        <button
                            onClick={(e) => {
                                e.preventDefault();
                                setData("ids", selectedIds);
                                setIsPopupOpen(true);
                                setIsMenuOpen(false);
                            }}
                            className="cursor-pointer w-full rounded-md flex items-center gap-3 px-3 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700 hover:text-yellow-500 dark:hover:text-yellow-400"
                        >
                            <Edit size={20} />
                            <span>Edit {selectedIds.length} Submission</span>
                        </button>
                        <button
                            onClick={(e) => {
                                e.preventDefault();
                                downloadSelected(selectedIds);
                                setIsMenuOpen(false);
                            }}
                            className="cursor-pointer w-full rounded-md flex items-center gap-3 px-3 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700"
                        >
                            <Download size={20} />
                            <span>
                                Download {selectedIds.length} Submission
                            </span>
                        </button>
                    </>
                )}
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
                    submission: createWidget(FileUpload, {
                        disabled: true,
                    }),
                    submitted_at: createWidget(TextField, {
                        type: "datetime",
                        formatter: (value, onChange) =>
                            onChange
                                ? new Date(value).toISOString()
                                : new Date(
                                      value || new Date()
                                  ).toLocaleString(),
                    }),
                    feedback: createWidget(FileUpload, {
                        multipleFiles: true,
                        maxFiles: 10,
                    }),
                }}
            >
                <div className="max-w-full flex justify-center flex-col gap-2 items-center">
                    {competitions && competitions.length > 0 && (
                        <>
                            <div className="flex max-w-full gap-4 p-2 bg-white dark:bg-gray-800 rounded-xl overflow-auto">
                                {competitions.map((competition) => (
                                    <div
                                        key={competition}
                                        onClick={() =>
                                            handleChangeMenu(
                                                statusMenu,
                                                competition
                                            )
                                        }
                                        className={`flex items-center gap-2 px-3 py-2 ${
                                            competitionMenu === competition &&
                                            "text-yellow-700 dark:text-yellow-500 bg-gray-100 dark:bg-gray-600"
                                        } hover:bg-gray-300 dark:hover:bg-gray-600 cursor-pointer rounded-lg font-semibold capitalize`}
                                    >
                                        <span>{competition}</span>
                                    </div>
                                ))}
                            </div>

                            <div className="flex max-w-full gap-4 p-2 bg-white dark:bg-gray-800 rounded-xl overflow-auto">
                                {Object.entries(status).map(([key, value]) => (
                                    <div
                                        key={key}
                                        onClick={() =>
                                            handleChangeMenu(
                                                key,
                                                competitionMenu
                                            )
                                        }
                                        className={`flex items-center gap-2 px-3 py-2 ${
                                            statusMenu === key &&
                                            "text-yellow-700 dark:text-yellow-500 bg-gray-100 dark:bg-gray-600"
                                        } hover:bg-gray-300 dark:hover:bg-gray-600 cursor-pointer rounded-lg font-semibold capitalize`}
                                    >
                                        <span>{value}</span>
                                    </div>
                                ))}
                            </div>
                        </>
                    )}
                </div>
            </DataTable>
        </>
    );
}

Submissions.layout = (page: ReactElement) => {
    return <MainLayout title="Submissions | Admin">{page}</MainLayout>;
};

export default Submissions;
