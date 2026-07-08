import PreloadableLink from "@/Components/PreloadableLink";
import MainLayout from "@/Layouts/MainLayout";
import { motion } from "framer-motion";
import { ArrowRightIcon, TriangleAlert, UserCircle, Users } from "lucide-react";
import { ReactElement } from "react";

export interface Team {
    id: number;
    public_id: string;
    name: string;
    members: {
        id: number;
        avatar: string | null;
    }[];
    competition: {
        name: string;
        url: string;
        phase: string;
        deadline: string;
        deleted_at: string | null;
    };
    deleted_at: string | null;
}

interface DashboardProps {
    locale: string;
    translations: AppPageProps["translations"];
    user: User;
    profileEmpty: boolean;
    teams: Team[];
}

function Dashboard({
    locale,
    translations,
    user,
    profileEmpty,
    teams,
}: DashboardProps) {
    const translation = translations["user-dashboard"];
    return (
        <motion.div
            initial={{ opacity: 0, y: 10 }}
            animate={{ opacity: 1, y: 0 }}
            exit={{ opacity: 0 }}
            transition={{ duration: 0.3 }}
            className="p-4 sm:p-6 lg:p-8"
        >
            <div className="mb-8">
                <h1 className="text-3xl font-bold tracking-tight">
                    {translation.title || "User Dashboard"}
                </h1>
                <p className="text-gray-500 dark:text-gray-400 mt-1">
                    {translation["greeting"] || "Welcome back,"} {user.name}!
                </p>
            </div>

            {profileEmpty && (
                <div className="mb-8 p-4 bg-yellow-400/80 dark:bg-yellow-900/70 border-l-4 border-yellow-600 rounded-r-lg shadow-lg">
                    <div className="flex flex-col md:flex-row gap-4">
                        <div className="flex items-center flex-grow">
                            <TriangleAlert size={40} />
                            <div className="ml-4">
                                <p className="font-semibold">
                                    {translation["incomplete-profile"] ||
                                        "Your profile is not complete!"}
                                </p>
                                <p className="text-sm mt-1">
                                    {translation["incomplete-profile-info"] ||
                                        "Please complete your profile to join the competition."}
                                </p>
                            </div>
                        </div>

                        <PreloadableLink
                            href={`/${locale}/settings#details-profile`}
                            className="flex items-center justify-center gap-2 ml-6 px-4 py-2 text-white bg-yellow-600 hover:bg-yellow-700 text-sm font-medium rounded-md focus:outline-none focus:ring-2 focus:ring-yellow-500 transition-colors"
                        >
                            {translation["complete-profile"] ||
                                "Complete Profile "}
                            <ArrowRightIcon size={20} />
                        </PreloadableLink>
                    </div>
                </div>
            )}

            <div className="space-y-12">
                <section>
                    <h2 className="text-2xl font-semibold mb-4">
                        {translation["my-competitions"] || "My Competitions"}
                    </h2>
                    <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        {teams && teams.length > 0 ? (
                            teams.map((team) => (
                                <div
                                    key={team.competition.name}
                                    className={`rounded-lg p-6 shadow-[3px_3px_0px_0px_rgba(0,0,0,1)] lg:shadow-[4px_4px_0px_0px_rgba(0,0,0,1)] dark:shadow-none
                                    ${
                                        team.competition.deleted_at
                                            ? "opacity-80 bg-gray-300/90 dark:bg-gray-800"
                                            : "bg-white border border-gray-200 hover:border-blue-500 hover:bg-blue-50 dark:bg-gray-900 dark:border-gray-600/50 dark:hover:border-blue-500 dark:hover:bg-gray-900/60 transition-all transform hover:-translate-y-1"
                                    }`}
                                >
                                    {team.competition.deleted_at && (
                                        <div className="-mx-6 -mt-6 mb-4 p-4 rounded-t-lg flex items-center justify-center bg-yellow-400/80 dark:bg-yellow-900/70">
                                            <TriangleAlert size={35} />
                                            <h3 className="font-semibold ml-4">
                                                {translation[
                                                    "competition-deleted"
                                                ] ||
                                                    "This competition has been deleted, please contact the administrator."}
                                            </h3>
                                        </div>
                                    )}
                                    <a href={team.competition.url || ""} className="hover:underline hover:text-blue-500">
                                        <h3 className="capitalize text-xl font-bold">
                                            {team.competition.name}
                                        </h3>
                                    </a>
                                    <p className="flex gap-2 text-sm text-gray-500 dark:text-gray-400 mt-1">
                                        <span>
                                            {translation["your-team"] ||
                                                "Your Team:"}
                                        </span>
                                        <span className="capitalize font-semibold">
                                            {team.name}
                                        </span>
                                    </p>

                                    <div className="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700">
                                        {team.competition.phase && (
                                            <>
                                                <p className="text-sm font-medium text-gray-600 dark:text-gray-300">
                                                    {translation[
                                                        "current-phase"
                                                    ] || "Current Phase:"}
                                                </p>
                                                <p className="text-base font-semibold text-blue-600 dark:text-blue-300">
                                                    {translation[
                                                        team.competition.phase
                                                    ] || team.competition.phase}
                                                </p>
                                            </>
                                        )}
                                        {team.competition.deadline && (
                                            <>
                                                <p className="text-sm font-medium text-gray-600 dark:text-gray-500 mt-3">
                                                    {translation[
                                                        "next-deadline"
                                                    ] || "Next Deadline:"}
                                                </p>
                                                <p className="text-base font-semibold text-red-600 dark:text-red-500">
                                                    {new Date(
                                                        team.competition.deadline
                                                    ).toLocaleString(
                                                        locale,
                                                        {
                                                            day: "numeric",
                                                            month: "short",
                                                            year: "numeric",
                                                            hour: "2-digit",
                                                            minute: "2-digit",
                                                            timeZoneName: "short",
                                                        }
                                                    )}
                                                </p>
                                            </>
                                        )}
                                    </div>
                                </div>
                            ))
                        ) : (
                            <p className="col-span-full mt-10 text-sm text-center text-gray-500 dark:text-gray-400">
                                {translation["no-competitions"] ||
                                    "You have not joined any competitions yet."}
                            </p>
                        )}
                    </div>
                </section>

                <section>
                    <h2 className="text-2xl font-semibold mb-4">
                        {translation["my-teams"] || "My Teams"}
                    </h2>
                    <div className="space-y-4">
                        {teams && teams.length > 0 ? (
                            teams.map((team, index) => (
                                <motion.div
                                    key={team.id}
                                    initial={{ opacity: 0, y: 20 }}
                                    animate={{ opacity: 1, y: 0 }}
                                    transition={{ delay: index * 0.1 }}
                                    className={`rounded-lg p-6 flex flex-col shadow-[3px_3px_0px_0px_rgba(0,0,0,1)] lg:shadow-[4px_4px_0px_0px_rgba(0,0,0,1)] dark:shadow-none
                                ${
                                    team.deleted_at
                                        ? "opacity-80 bg-gray-300/90 dark:bg-gray-800"
                                        : "border border-gray-200 bg-white dark:bg-gray-900 dark:border-gray-600/50"
                                }`}
                                >
                                    {team.deleted_at && (
                                        <div className="-mx-6 -mt-6 mb-4 p-6 rounded-t-lg flex items-center justify-center bg-yellow-400/80 dark:bg-yellow-900/70">
                                            <TriangleAlert size={35} />
                                            <h3 className="font-semibold ml-4">
                                                {translation["team-blocked"] ||
                                                    "Your team has been blocked, please contact the administrator."}
                                            </h3>
                                        </div>
                                    )}

                                    <div className="flex flex-col md:flex-row md:items-center justify-between">
                                        <div>
                                            <h3 className="text-lg font-bold capitalize">
                                                {team.name}
                                            </h3>

                                            <p className="text-sm text-gray-500 dark:text-gray-400 mb-4">
                                                {translation["competing-in"] ||
                                                    "Competing in:"}{" "}
                                                {team.competition.name}
                                            </p>

                                            <div className="flex items-center gap-2 mt-2 sm:mt-0">
                                                <Users
                                                    size={20}
                                                    className="text-gray-500 dark:text-gray-400"
                                                />
                                                <div className="flex -space-x-2">
                                                    {team.members.map(
                                                        (member) =>
                                                            member.avatar ? (
                                                                <img
                                                                    key={
                                                                        member.id
                                                                    }
                                                                    src={
                                                                        member.avatar
                                                                    }
                                                                    width={32}
                                                                    height={32}
                                                                    className="rounded-full border-2 border-gray-800"
                                                                />
                                                            ) : (
                                                                <UserCircle
                                                                    key={
                                                                        member.id
                                                                    }
                                                                    className="bg-white dark:bg-black rounded-full"
                                                                    size={32}
                                                                />
                                                            )
                                                    )}
                                                </div>
                                                <span className="text-gray-500 dark:text-gray-400 text-sm">
                                                    {team.members.length}{" "}
                                                    {translation["members"] ||
                                                        "members"}
                                                </span>
                                            </div>
                                        </div>

                                        <motion.div
                                            whileHover={{ scale: 1.05 }}
                                            whileTap={{ scale: 0.95 }}
                                            className="mt-8 md:mt-0 flex justify-end w-full md:w-auto"
                                        >
                                            <PreloadableLink
                                                href={window.location.pathname.replace(
                                                    "dashboard",
                                                    `teams/${team.public_id}`
                                                )}
                                                preserveScroll
                                                className="flex items-center gap-2 px-4 py-2 bg-gray-200 dark:bg-gray-700 text-sm font-medium rounded-md hover:bg-gray-300 dark:hover:bg-gray-600 focus:outline-none transition-colors"
                                            >
                                                {translation["see-more-info"] ||
                                                    "See More Info"}
                                                <ArrowRightIcon size={20} />
                                            </PreloadableLink>
                                        </motion.div>
                                    </div>
                                </motion.div>
                            ))
                        ) : (
                            <p className="mt-10 text-sm text-center text-gray-500 dark:text-gray-400">
                                {translation["no-teams"] ||
                                    "You have not joined any teams yet."}
                            </p>
                        )}
                    </div>
                </section>
            </div>
        </motion.div>
    );
}

Dashboard.layout = (page: ReactElement) => {
    return <MainLayout title="User Dashboard">{page}</MainLayout>;
};

export default Dashboard;
