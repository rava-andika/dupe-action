import PreloadableLink from "@/Components/PreloadableLink";
import MainLayout from "@/Layouts/MainLayout";
import { useForm } from "@inertiajs/react";
import { AnimatePresence, motion } from "framer-motion";
import {
    ArrowRight,
    Crown,
    LogIn,
    TriangleAlert,
    UserCircle,
    X,
} from "lucide-react";
import { ReactElement } from "react";

interface Member {
    id: number;
    name: string;
    avatar: string | null;
}

export interface Team {
    id: number;
    name: string;
    invite_code: string;
    leader_id: number;
    members: Member[];
    competition: {
        name: string;
    };
}

function JoinPopup({
    team,
    profileEmpty,
    locale,
    translations,
}: {
    team: Team;
    profileEmpty: boolean;
    locale: string;
    translations: AppPageProps["translations"];
}) {
    const translation = translations["user-teams"];
    const { post, processing } = useForm({
        invite_code: team.invite_code,
    });

    const handleSubmit = (e: React.FormEvent<HTMLFormElement>) => {
        e.preventDefault();
        post(`/${locale}/user/teams/join`, {
            preserveScroll: true,
        });
    };

    return (
        <AnimatePresence>
            <div className="fixed inset-0 z-50 flex items-center justify-center">
                {/* Modal Content */}
                <motion.div
                    initial={{ opacity: 0, scale: 0.95, y: 20 }}
                    animate={{ opacity: 1, scale: 1, y: 0 }}
                    exit={{ opacity: 0, scale: 0.95, y: 20 }}
                    transition={{ type: "spring", stiffness: 300, damping: 25 }}
                    className="relative z-10 w-full max-w-md overflow-hidden rounded-2xl bg-white/80 dark:bg-gray-800/50 border border-gray-200 dark:border-gray-700 shadow-[3px_3px_0px_0px_rgba(0,0,0,1)] lg:shadow-[4px_4px_0px_0px_rgba(0,0,0,1)] dark:shadow-none"
                >
                    <div className="p-8 text-center">
                        <p className="text-sm font-medium text-blue-600 dark:text-blue-400 uppercase tracking-wider">
                            {team.competition.name}
                        </p>
                        <h1 className="mt-2 text-3xl font-bold capitalize">
                            {translation["you-are-invited"] ||
                                "You're invited to join"}{" "}
                            "{team.name}"
                        </h1>

                        <div className="mt-8 text-center">
                            <h2 className="text-sm font-bold text-gray-500 dark:text-gray-400 uppercase tracking-widest">
                                {translation["team-members"] || "Team Members"}{" "}
                                ({team.members.length})
                            </h2>
                            <div className="mt-4 flex flex-wrap justify-center gap-x-6 gap-y-8">
                                {team.members.map((member) => (
                                    <div
                                        key={member.id}
                                        className="flex flex-col items-center text-center w-24"
                                    >
                                        <div className="relative">
                                            {member.avatar ? (
                                                <img
                                                    src={member.avatar}
                                                    alt={member.name}
                                                    className={`w-16 h-16 rounded-full border-4 object-cover ${
                                                        member.id ===
                                                        team.leader_id
                                                            ? "border-yellow-400"
                                                            : "border-gray-300 dark:border-gray-600"
                                                    }`}
                                                />
                                            ) : (
                                                <UserCircle
                                                    className={`w-16 h-16 rounded-full border-4 text-gray-400 bg-gray-100 dark:text-gray-500 dark:bg-gray-700 ${
                                                        member.id ===
                                                        team.leader_id
                                                            ? "border-yellow-400"
                                                            : "border-gray-300 dark:border-gray-600"
                                                    }`}
                                                />
                                            )}

                                            {member.id === team.leader_id && (
                                                <div className="absolute -top-2 -right-2 bg-yellow-400 p-1 rounded-full shadow-lg">
                                                    <Crown
                                                        size={16}
                                                        className="text-gray-900"
                                                    />
                                                </div>
                                            )}
                                        </div>
                                        <span className="mt-2 text-sm font-semibold capitalize break-words text-gray-800 dark:text-gray-200">
                                            {member.name}
                                        </span>
                                    </div>
                                ))}
                            </div>
                        </div>
                    </div>

                    {/* Action Buttons */}
                    {profileEmpty ? (
                        <div className="flex flex-col sm:flex-row items-center justify-between gap-4 p-4 border-t border-gray-200 dark:border-gray-700 bg-yellow-400/20 text-yellow-800 dark:text-yellow-200">
                            <div className="flex items-center gap-3">
                                <TriangleAlert className="w-8 h-8 sm:w-6 sm:h-6 text-yellow-500" />
                                <p className="text-sm font-medium">
                                    {translation["please-complete-profile"] ||
                                        "Please complete your profile first."}
                                </p>
                            </div>
                            <PreloadableLink
                                href={`/${locale}/settings#details-profile`}
                                className="flex-shrink-0 whitespace-nowrap inline-flex items-center justify-center gap-2 px-4 py-2 text-white bg-yellow-500 hover:bg-yellow-600 text-sm font-medium rounded-md focus:outline-none transition-colors"
                            >
                                {translation["complete-profile-button"] ||
                                    "Complete Profile"}
                                <ArrowRight size={16} />
                            </PreloadableLink>
                        </div>
                    ) : (
                        <form
                            onSubmit={handleSubmit}
                            className="grid grid-cols-2 gap-px bg-gray-200 dark:bg-gray-700"
                        >
                            <PreloadableLink
                                href={`/${locale}/user/teams`}
                                className="flex items-center justify-center gap-2 p-4 font-semibold text-gray-700 bg-white/50 hover:bg-gray-100 dark:text-gray-300 dark:bg-gray-800/80 dark:hover:bg-gray-800 transition-colors"
                            >
                                <X size={20} />
                                {translation["cancel"] || "Cancel"}
                            </PreloadableLink>

                            <button
                                type="submit"
                                disabled={processing}
                                className="cursor-pointer flex items-center justify-center gap-2 p-4 font-semibold text-white bg-green-600 hover:bg-green-700 disabled:bg-green-400 disabled:cursor-not-allowed dark:bg-green-600/80 dark:hover:bg-green-600 dark:disabled:bg-green-800/50 transition-colors"
                            >
                                <LogIn size={20} />
                                {processing
                                    ? translation["joining"] || "Joining..."
                                    : translation["join-team"] || "Join Team"}
                            </button>
                        </form>
                    )}
                </motion.div>
            </div>
        </AnimatePresence>
    );
}

JoinPopup.layout = (page: ReactElement) => {
    const props = page.props as any;
    return (
        <MainLayout
            title={`Join ${props.team.name} | ${props.team.competition.name}`}
        >
            {page}
        </MainLayout>
    );
};

export default JoinPopup;
