import React, {
    ReactElement,
    ReactNode,
    useEffect,
    useMemo,
    useRef,
    useState,
} from "react";
import { Link, useForm } from "@inertiajs/react";
import { AnimatePresence, motion } from "framer-motion";
import {
    Swords,
    Info,
    Upload,
    Copy,
    Check,
    Crown,
    UserCircle,
    ArrowRight,
    LucideIcon,
    Share2,
    Link2Icon,
    Ban,
    Search,
    X,
    TriangleAlert,
    PlusCircle,
    LoaderCircle,
    ShieldQuestion,
    XCircle,
    Clock,
    ShieldCheck,
    MessageSquare,
} from "lucide-react";
import MainLayout from "@/Layouts/MainLayout";
import { Team } from "./JoinPopup";
import FileUpload from "@/Components/Form/FileUpload";

interface TeamView extends Team {
    public_id: string;
    registration_end: string;
    registrationStatus: "open" | "not-started" | "closed";
    bans: Team["members"];
    competition: {
        name: string;
        url: string;
        min_team_size: number;
        price: number;
    };
    members: {
        id: number;
        name: string;
        avatar: string | null;
        profileEmpty: boolean;
    }[];
    registrations: {
        id: number;
        status: "pending" | "approved" | "rejected";
        payment_proof: string | null;
        submitted_at: string | null;
        notes: Record<string, string> | null;
        group_link: string;
    }[];
    submissions: {
        id: number;
        status: "pending" | "reviewed";
        submission: string[] | null;
        submitted_at: string | null;
        feedback: string[] | null;
    }[];
    SubmissionStatus: {
        canSubmit: boolean;
        closesAt: string | null;
        nextOpenAt: string | null;
    };
}

interface paymentMethods {
    method: string;
    accountNumber: string;
    holderName: string;
}

function TeamViewPage({
    team,
    user,
    paymentMethods,
    locale,
    translations,
}: {
    team: TeamView;
    user: User;
    paymentMethods: paymentMethods[];
    locale: string;
    translations: AppPageProps["translations"];
}) {
    const translation = translations["user-teams"];
    const sortedRegistrations = useMemo(
        () =>
            team.registrations.sort(
                (a, b) =>
                    new Date(b.submitted_at ?? new Date()).getTime() -
                    new Date(a.submitted_at ?? new Date()).getTime()
            ),
        [team.registrations]
    );
    const NavLink = ({
        activeTab,
        icon: Icon,
        tab,
        children,
    }: {
        activeTab: boolean;
        icon: LucideIcon;
        tab: "details" | "submissions";
        children: ReactNode;
    }) => (
        <Link
            href={`/${locale}/user/teams/${team.public_id}${
                tab === "submissions" ? "/submission" : ""
            }`}
            className={`cursor-pointer flex w-full items-center gap-3 rounded-md px-3 py-2 text-sm font-medium transition-colors ${
                activeTab
                    ? "bg-blue-600 text-white shadow"
                    : "text-gray-600 hover:bg-gray-300 dark:text-gray-300 dark:hover:bg-gray-700"
            }`}
        >
            <Icon size={16} />
            {children}
        </Link>
    );

    return (
        <div className="p-4 sm:p-6 lg:p-8">
            <div className="grid grid-cols-1 gap-8 md:grid-cols-[240px_1fr]">
                {/* Left Navigation */}
                <aside>
                    <nav className="space-y-2">
                        <NavLink
                            activeTab={!team.submissions}
                            tab="details"
                            icon={Info}
                        >
                            {translation["details"] || "Details"}
                        </NavLink>
                        <NavLink
                            activeTab={!!team.submissions}
                            tab="submissions"
                            icon={Upload}
                        >
                            {translation["submission"] || "Submission"}
                        </NavLink>
                    </nav>
                </aside>

                {/* Right Content Area */}
                <AnimatePresence mode="wait">
                    <motion.div
                        initial={{ opacity: 0, y: 10 }}
                        animate={{ opacity: 1, y: 0 }}
                        exit={{ opacity: 0, y: -10 }}
                        transition={{ duration: 0.2 }}
                        className="max-w-full min-w-0"
                    >
                        {team.submissions ? (
                            <SubmissionContent
                                translation={translation}
                                team={team}
                                sortedRegistrations={sortedRegistrations}
                                locale={locale}
                            />
                        ) : (
                            <DetailsContent
                                team={team}
                                user={user}
                                paymentMethods={paymentMethods}
                                locale={locale}
                                translation={translation}
                                sortedRegistrations={sortedRegistrations}
                            />
                        )}
                    </motion.div>
                </AnimatePresence>
            </div>
        </div>
    );
}

TeamViewPage.layout = (page: ReactElement) => {
    const props = page.props as any;
    return (
        <MainLayout
            title={`Team ${props.team.name} | ${props.team.competition.name}`}
        >
            {page}
        </MainLayout>
    );
};

export default TeamViewPage;

function DetailsContent({
    team,
    user,
    paymentMethods,
    locale,
    translation,
    sortedRegistrations,
}: {
    team: TeamView;
    user: User;
    paymentMethods: paymentMethods[];
    locale: string;
    translation: Record<string, string>;
    sortedRegistrations: TeamView["registrations"];
}) {
    const [isRegisterModalOpen, setRegisterModalOpen] = useState(false);
    const [isBanListModalOpen, setBanListModalOpen] = useState(false);
    const [copied, setCopied] = useState(false);
    const [canShare, setCanShare] = useState(false);
    const [shared, setShared] = useState(false);
    const [searchQuery, setSearchQuery] = useState("");
    const popupRef = useRef<HTMLDivElement>(null);

    // Memoized filtering for performance. The list only re-filters when the search query or the base list changes.
    const filteredBannedUsers = useMemo(() => {
        if (!searchQuery) {
            return team.bans.filter((user) => user.name);
        }
        return team.bans.filter((user) =>
            user.name.toLowerCase().includes(searchQuery.toLowerCase())
        );
    }, [searchQuery, team.bans]);

    const handleCopyToClipboard = (text: string) => {
        navigator.clipboard.writeText(text);
        setCopied(true);
        setTimeout(() => setCopied(false), 2000); // Reset after 2 seconds
    };

    const handleShare = async (useNavigatorShare = false) => {
        const url =
            window.location.origin +
            window.location.pathname.replace(
                team.public_id,
                `join/${team.invite_code}`
            );

        if (!useNavigatorShare) {
            navigator.clipboard.writeText(url);
            setShared(true);
            setTimeout(() => setShared(false), 2000); // Reset after 2 seconds
            return;
        }

        try {
            await navigator.share({
                title:
                    translation["invite-title"]
                        ?.replace(":name", team.name)
                        ?.replace(":competition-name", team.competition.name) ||
                    `Let's join ${team.name} on ${team.competition.name}`,
                url,
            });
            setShared(true);
            setTimeout(() => setShared(false), 2000); // Reset after 2 seconds
        } catch (error) {
            console.error("Error sharing:", error);
        }
    };

    useEffect(() => {
        if (navigator.share !== undefined) {
            setCanShare(true);
        }
    }, []);

    useEffect(() => {
        const handleClickOutside = (e: MouseEvent) => {
            if (e.target instanceof Node) {
                if (!popupRef.current?.contains(e.target))
                    setBanListModalOpen(false);
            }
        };

        document.addEventListener("mousedown", handleClickOutside);
        return () => {
            document.removeEventListener("mousedown", handleClickOutside);
        };
    }, [popupRef]);

    const shareAction = canShare
        ? () => handleShare(true)
        : () => handleShare(false);
    const ShareIcon = canShare ? Share2 : Link2Icon;

    return (
        <div className="space-y-8">
            {/* Header */}
            <div className="flex flex-col md:flex-row md:items-center gap-4 md:justify-between">
                <div>
                    <h1 className="text-3xl font-bold capitalize">
                        {team.name}
                    </h1>
                    <a
                        href={team.competition.url || ""}
                        className="text-gray-500 dark:text-gray-400 hover:underline hover:text-blue-500 dark:hover:text-blue-400"
                    >
                        <p className="mt-1 flex items-center gap-2">
                            <Swords size={18} />
                            {translation["competing-in"] || "Competing in"} "
                            {team.competition.name}"
                        </p>
                    </a>
                </div>

                <div className="max-w-max text-sm bg-white dark:bg-gray-800 font-bold rounded-md p-2 shadow-sm border dark:border-gray-700">
                    {(() => {
                        switch (sortedRegistrations[0]?.status) {
                            case "approved":
                                return (
                                    <div className="flex items-center gap-2 text-green-600 dark:text-green-400">
                                        <ShieldCheck size={20} />
                                        <span>
                                            {translation["verified"] ||
                                                "Verified"}
                                        </span>
                                    </div>
                                );
                            case "pending":
                                return (
                                    <div className="flex items-center gap-2 text-yellow-600 dark:text-yellow-400">
                                        <Clock size={20} />
                                        <span>
                                            {translation["waiting-approval"] ||
                                                "Waiting Approval"}
                                        </span>
                                    </div>
                                );
                            case "rejected":
                                return (
                                    <div className="flex items-center gap-2 text-red-600 dark:text-red-400">
                                        <XCircle size={20} />
                                        <span>
                                            {translation["rejected"] ||
                                                "Rejected"}
                                        </span>
                                    </div>
                                );
                            default:
                                return (
                                    <div className="flex items-center gap-2 text-gray-600 dark:text-white">
                                        <ShieldQuestion size={20} />
                                        <span>
                                            {translation["unverified"] ||
                                                "Unverified"}
                                        </span>
                                    </div>
                                );
                        }
                    })()}
                </div>
            </div>

            {/* Rejected Team Alert */}
            {sortedRegistrations[0]?.status === "rejected" && (
                <div className="mb-8 p-4 bg-yellow-400/80 dark:bg-yellow-900/70 border-l-4 border-yellow-600 rounded-r-lg shadow-lg">
                    <div className="flex flex-col md:flex-row gap-4">
                        <div className="flex items-center flex-grow">
                            <TriangleAlert size={40} />
                            <div className="ml-4">
                                <p className="font-semibold">
                                    {translation["team-rejected"] ||
                                        "Your team registration has been rejected!"}
                                </p>
                                <p className="text-sm mt-1">
                                    {`${translation["notes"] || "Notes: "} ${
                                        sortedRegistrations[0].notes?.[locale]
                                    }`}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            )}

            {/* Show this box only if a group link exists */}
            {sortedRegistrations[0]?.group_link && (
                <div className="mb-8 p-4 bg-sky-100 dark:bg-sky-900/70 border-l-4 border-sky-500 rounded-r-lg shadow-lg">
                    <div className="flex flex-col sm:flex-row gap-4 items-center">
                        <div className="flex items-center flex-grow gap-2">
                            <MessageSquare
                                size={40}
                                className="text-sky-600 dark:text-sky-400"
                            />

                            <div className="ml-4">
                                <p className="font-semibold">
                                    {translation["group-link-title"] ||
                                        "Official Competition Group"}
                                </p>
                                <p className="text-sm mt-1">
                                    {translation["group-link-description"] ||
                                        "Join this group to get updates on this competition."}
                                </p>
                            </div>
                        </div>

                        <div className="flex-shrink-0 mt-2 md:mt-0">
                            <a
                                href={sortedRegistrations[0].group_link}
                                target="_blank"
                                rel="noopener noreferrer"
                                className="inline-flex items-center px-4 py-2 bg-sky-600 hover:bg-sky-700 text-white font-bold rounded-lg shadow transition-colors"
                            >
                                {translation["join-group-button"] ||
                                    "Join Group"}
                            </a>
                        </div>
                    </div>
                </div>
            )}

            {/* Info Cards */}
            <div className="grid grid-cols-1 gap-5 sm:grid-cols-2">
                {/* Invite Code Card */}
                <div className="rounded-lg border bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                    <div className="flex items-center justify-between gap-2">
                        <div>
                            <h3>
                                {translation["invite-code"] || "Invite Code"}
                            </h3>
                            <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                {translation["share-invite-code"] ||
                                    "Share this code to invite members."}
                            </p>
                        </div>

                        <button
                            onClick={shareAction}
                            className="cursor-pointer rounded p-2 text-gray-500 hover:bg-gray-200 dark:text-gray-400 dark:hover:bg-gray-700"
                        >
                            {shared ? (
                                <Check size={18} className="text-green-500" />
                            ) : (
                                <ShareIcon size={18} />
                            )}
                        </button>
                    </div>

                    <div className="mt-3 flex items-center justify-between gap-2 rounded-md bg-gray-100 p-2 dark:bg-gray-900">
                        <span className="font-mono text-gray-700 dark:text-gray-300">
                            {team.invite_code}
                        </span>

                        <button
                            onClick={() =>
                                handleCopyToClipboard(team.invite_code)
                            }
                            className="cursor-pointer rounded p-2 text-gray-500 hover:bg-gray-200 dark:text-gray-400 dark:hover:bg-gray-700"
                        >
                            {copied ? (
                                <Check size={18} className="text-green-500" />
                            ) : (
                                <Copy size={18} />
                            )}
                        </button>
                    </div>
                </div>

                {/* Register Card */}
                <div className="rounded-lg border bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                    <h3>
                        {translation["finalize-registration"] ||
                            "Finalize Registration"}
                    </h3>
                    <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        {translation["lock-your-team"] ||
                            "Lock in your team for the competition."}
                    </p>

                    <div className="mt-3 flex items-center gap-2">
                        {team.registrationStatus === "open" ? (
                            <>
                                <p className="font-semibold">
                                    {translation["registration-ends"] ||
                                        "Registration Ends:"}
                                </p>
                                <p className="font-semibold text-red-600 dark:text-red-500">
                                    {new Date(
                                        team.registration_end
                                    ).toLocaleString(locale, {
                                        day: "numeric",
                                        month: "short",
                                        year: "numeric",
                                        hour: "2-digit",
                                        minute: "2-digit",
                                        timeZoneName: "short",
                                    })}
                                </p>
                            </>
                        ) : (
                            <p className="font-semibold text-red-600 dark:text-red-500">
                                {translation[team.registrationStatus] ||
                                    `Registration ${team.registrationStatus}`}
                            </p>
                        )}
                    </div>

                    <button
                        onClick={() => setRegisterModalOpen(true)}
                        className="cursor-pointer mt-3 inline-flex w-full items-center justify-center gap-2 rounded-md bg-blue-600 px-4 py-2 font-semibold text-white shadow-sm hover:bg-blue-700 transition-colors"
                    >
                        {translation["register-team"] || "Register Team"}
                        <ArrowRight size={16} />
                    </button>
                </div>
            </div>

            {/* Members List */}
            <div>
                <h2 className="text-xl font-bold">
                    {translation["team-members"] || "Team Members"} (
                    {team.members.length})
                </h2>

                {team.leader_id === user.id && team.bans.length > 0 && (
                    <button
                        onClick={() => setBanListModalOpen(true)}
                        className="cursor-pointer my-3 inline-flex rounded-md bg-red-600 px-4 py-2 font-semibold text-white shadow-sm hover:bg-red-700 transition-colors"
                    >
                        {translation["ban-list"] || "Ban List"} (
                        {team.bans.length})
                    </button>
                )}

                <div className="mt-4 flow-root">
                    <ul
                        role="list"
                        className="-my-5 divide-y divide-gray-200 dark:divide-gray-700"
                    >
                        {team.members.map((member, index) => (
                            <motion.li
                                key={member.id}
                                className="py-4"
                                initial={{ opacity: 0, x: -20 }}
                                animate={{ opacity: 1, x: 0 }}
                                transition={{ delay: index * 0.05 }}
                            >
                                <div className="flex items-center justify-between">
                                    <div className="flex items-center gap-3">
                                        {member.avatar ? (
                                            <img
                                                key={member.id}
                                                src={member.avatar}
                                                width={32}
                                                height={32}
                                                className="rounded-full border-2 border-gray-800"
                                            />
                                        ) : (
                                            <UserCircle
                                                key={member.id}
                                                className="bg-white dark:bg-black rounded-full"
                                                size={32}
                                            />
                                        )}
                                        <div>
                                            <p className="truncate text-sm capitalize">
                                                {member.name}{" "}
                                                {user.id === member.id &&
                                                    `(${
                                                        translation["you"] ||
                                                        "You"
                                                    })`}
                                            </p>
                                            <p className="truncate text-sm text-gray-500 dark:text-gray-400">
                                                {member.id === team.leader_id
                                                    ? translation[
                                                          "team-leader"
                                                      ] || "Team Leader"
                                                    : translation["members"] ||
                                                      "Members"}
                                            </p>
                                        </div>
                                    </div>

                                    {member.id === team.leader_id && (
                                        <Crown
                                            size={30}
                                            className="text-yellow-600 dark:text-yellow-500"
                                        />
                                    )}

                                    {sortedRegistrations[0]?.status !==
                                        "approved" &&
                                        team.registrationStatus !== "closed" &&
                                        user.id === team.leader_id &&
                                        member.id !== user.id && (
                                            <Link
                                                href={`${window.location.pathname}/members/${member.id}`}
                                                onBefore={() =>
                                                    window.confirm(
                                                        translation[
                                                            "confirm-ban-member"
                                                        ] ||
                                                            "Are you sure you want to ban this member?"
                                                    )
                                                }
                                                method="delete"
                                                className="cursor-pointer inline-flex items-center gap-2 rounded-md px-3 py-2 text-sm font-semibold text-red-600 transition-colors hover:bg-red-500/10 dark:text-red-500"
                                            >
                                                <Ban size={16} />
                                                <span className="capitalize">
                                                    {translation["ban"] ||
                                                        "Ban"}
                                                </span>
                                            </Link>
                                        )}
                                </div>
                            </motion.li>
                        ))}
                    </ul>
                </div>
            </div>

            {/* Popup Modal */}
            <AnimatePresence mode="wait">
                {(isRegisterModalOpen || isBanListModalOpen) && (
                    <motion.div
                        initial={{ opacity: 0 }}
                        animate={{ opacity: 1 }}
                        exit={{ opacity: 0 }}
                        transition={{ duration: 0.3 }}
                        className="overflow-y-auto inset-0 fixed bg-black/50 z-100 flex items-start justify-center"
                    >
                        {isBanListModalOpen && team.leader_id === user.id && (
                            <motion.div
                                ref={popupRef}
                                initial={{ opacity: 0, scaleY: 0 }}
                                animate={{ opacity: 1, scaleY: 1 }}
                                exit={{ opacity: 0, scaleY: 0 }}
                                transition={{
                                    duration: 0.4,
                                    ease: "easeOut",
                                }}
                                className="my-auto px-4 py-8 bg-white dark:bg-gray-900 relative z-10 h-[70vh] w-full max-w-md flex-col overflow-hidden rounded-xl"
                            >
                                {/* Header */}
                                <div className="flex items-center justify-between border-b border-gray-200 p-4 dark:border-gray-700">
                                    <h2 className="text-lg font-bold text-gray-900 dark:text-white">
                                        {translation["banned-members"] ||
                                            "Banned Members"}
                                    </h2>
                                    <button
                                        onClick={() =>
                                            setBanListModalOpen(false)
                                        }
                                        className="rounded-full p-1 text-gray-500 hover:bg-gray-200 dark:text-gray-400 dark:hover:bg-gray-700"
                                    >
                                        <X size={20} />
                                    </button>
                                </div>

                                {/* Search Input */}
                                <div className="border-b border-gray-200 p-4 dark:border-gray-700">
                                    <div className="relative">
                                        <Search className="pointer-events-none absolute left-3 top-1/2 h-5 w-5 -translate-y-1/2 text-gray-400" />
                                        <input
                                            type="text"
                                            placeholder={
                                                translation["search-by-name"] ||
                                                "Search by name..."
                                            }
                                            value={searchQuery}
                                            onChange={(e) =>
                                                setSearchQuery(e.target.value)
                                            }
                                            className="w-full rounded-md border border-gray-300 bg-gray-50 py-2 pl-10 pr-4 text-gray-900 focus:border-blue-500 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white dark:placeholder-gray-400"
                                        />
                                    </div>
                                </div>

                                {/* Banned Users List */}
                                <div className="flex-1 overflow-y-auto p-4">
                                    <AnimatePresence>
                                        {filteredBannedUsers.length > 0 ? (
                                            <ul className="space-y-3">
                                                {filteredBannedUsers.map(
                                                    (user) => (
                                                        <li
                                                            key={user.id}
                                                            className="flex items-center justify-between gap-4"
                                                        >
                                                            <div className="flex items-center gap-3">
                                                                {user.avatar ? (
                                                                    <img
                                                                        src={
                                                                            user.avatar
                                                                        }
                                                                        alt={
                                                                            user.name
                                                                        }
                                                                        className="h-10 w-10 rounded-full object-cover"
                                                                    />
                                                                ) : (
                                                                    <UserCircle className="h-10 w-10 text-gray-400" />
                                                                )}
                                                                <span className="font-medium text-gray-800 dark:text-gray-200 capitalize">
                                                                    {user.name}
                                                                </span>
                                                            </div>
                                                            <Link
                                                                href={`${window.location.pathname}/bans/${user.id}`}
                                                                method="delete"
                                                                className="cursor-pointer inline-flex items-center gap-2 rounded-md px-3 py-1.5 text-sm font-semibold text-blue-600 transition-colors hover:bg-blue-500/10 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 dark:text-blue-400 dark:hover:bg-blue-500/10 dark:focus:ring-offset-gray-800"
                                                            >
                                                                <Ban
                                                                    size={16}
                                                                />
                                                                {translation[
                                                                    "unban"
                                                                ] || "Unban"}
                                                            </Link>
                                                        </li>
                                                    )
                                                )}
                                            </ul>
                                        ) : (
                                            <div className="flex flex-col items-center justify-center pt-16 text-center">
                                                <Search className="h-12 w-12 text-gray-400" />
                                                <h3 className="mt-2 text-lg font-medium text-gray-900 dark:text-white">
                                                    {translation[
                                                        "no-results-found"
                                                    ] || "No Results Found"}
                                                </h3>
                                                <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                                    {translation[
                                                        "no-banned-users"
                                                    ] ||
                                                        "No banned users match your search."}
                                                </p>
                                            </div>
                                        )}
                                    </AnimatePresence>
                                </div>
                            </motion.div>
                        )}

                        {isRegisterModalOpen && (
                            <RegistrationCard
                                team={team}
                                user={user}
                                setRegisterModalOpen={setRegisterModalOpen}
                                paymentMethods={paymentMethods}
                                locale={locale}
                                sortedRegistrations={sortedRegistrations}
                                translation={translation}
                            />
                        )}
                    </motion.div>
                )}
            </AnimatePresence>
        </div>
    );
}

function RegistrationCard({
    team,
    user,
    setRegisterModalOpen,
    paymentMethods,
    locale,
    sortedRegistrations,
    translation,
}: {
    team: TeamView;
    user: User;
    setRegisterModalOpen: React.Dispatch<React.SetStateAction<boolean>>;
    paymentMethods: paymentMethods[];
    locale: string;
    sortedRegistrations: TeamView["registrations"];
    translation: Record<string, string>;
}) {
    const [activeTab, setActiveTab] = useState<string | number>("new");
    const profileEmptyMembers = useMemo(() => {
        return team.members.filter((member) => member.profileEmpty);
    }, [team.members]);
    const popupRef = useRef<HTMLDivElement>(null);
    const canRegister = useMemo(
        () =>
            team.registrationStatus === "open" &&
            !team.registrations.some(
                (registration) => registration.status !== "rejected"
            ) &&
            team.members.length >= team.competition.min_team_size &&
            profileEmptyMembers.length <= 0,
        [team.registrationStatus, team.registrations]
    );

    const [isUploadingFile, setIsUploadingFile] = useState<boolean>(false);
    const { post, processing, data, setData, errors, isDirty, reset } =
        useForm<{
            payment_proof: any;
        }>({
            payment_proof: "",
        });

    const handleRegisterTeam = (e: React.FormEvent) => {
        e.preventDefault();
        post(`${window.location.pathname}/register`, {
            onSuccess: () => handleClose(true),
        });
    };

    const handleClose = (isPost?: boolean) => {
        if (
            !isPost &&
            isDirty &&
            !confirm(
                translation["discard-changes"] ||
                    "Are you sure you want to discard changes?"
            )
        ) {
            return;
        }
        reset();
        setRegisterModalOpen(false);
    };

    // Reset to the 'new' tab when modal opens and registration is possible
    useEffect(() => {
        if (canRegister) {
            setActiveTab("new");
        } else if (sortedRegistrations.length > 0) {
            // If cannot register, show the latest registration history by default
            setActiveTab(sortedRegistrations[0].id);
        }
    }, [canRegister]);

    useEffect(() => {
        const handleClickOutside = (e: MouseEvent) => {
            if (e.target instanceof Node) {
                if (!popupRef.current?.contains(e.target)) handleClose();
            }
        };

        const handleBeforeUnload = (event: BeforeUnloadEvent) => {
            event.preventDefault();
            event.returnValue = ""; // Required for some older browsers
        };

        document.addEventListener("mousedown", handleClickOutside);
        if (isDirty) {
            window.addEventListener("beforeunload", handleBeforeUnload);
        }

        return () => {
            document.removeEventListener("mousedown", handleClickOutside);
            window.removeEventListener("beforeunload", handleBeforeUnload);
        };
    }, [popupRef, isDirty]);

    return (
        <motion.div
            ref={popupRef}
            initial={{ opacity: 0, scaleY: 0 }}
            animate={{ opacity: 1, scaleY: 1 }}
            exit={{ opacity: 0, scaleY: 0 }}
            transition={{
                duration: 0.4,
                ease: "easeOut",
            }}
            className="relative my-auto min-w-full md:min-w-2/3 px-4 py-8 bg-white dark:bg-gray-900 border-1 border-black/30 dark:border-white/30 rounded-lg"
        >
            <button
                className="cursor-pointer absolute top-4 right-4 mb-4"
                onClick={() => handleClose()}
            >
                <X size={28} />
            </button>

            {/* navbar for switch between registrations */}
            {(canRegister || sortedRegistrations.length > 0) && (
                <div className="flex border-b border-gray-200 dark:border-gray-700 px-4 pt-4 overflow-x-auto">
                    {canRegister && (
                        <button
                            onClick={() => setActiveTab("new")}
                            className={`cursor-pointer flex items-center gap-2 py-3 px-4 text-sm font-medium border-b-2 transition-colors ${
                                activeTab === "new"
                                    ? "border-blue-500 text-blue-600 dark:text-blue-400"
                                    : "border-transparent text-gray-500 hover:text-gray-700 dark:hover:text-gray-300"
                            }`}
                        >
                            <PlusCircle size={16} />
                            {translation["register"] || "Register"}
                        </button>
                    )}

                    {sortedRegistrations.length > 0 &&
                        sortedRegistrations.map((reg, index) => (
                            <button
                                key={reg.id}
                                onClick={() => setActiveTab(reg.id)}
                                className={`cursor-pointer py-3 px-4 text-sm font-medium border-b-2 transition-colors ${
                                    activeTab === reg.id
                                        ? "border-blue-500 text-blue-600 dark:text-blue-400"
                                        : "border-transparent text-gray-500 hover:text-gray-700 dark:hover:text-gray-300"
                                }`}
                            >
                                {translation["history"] || "History"} #
                                {sortedRegistrations.length - index}
                            </button>
                        ))}
                </div>
            )}

            <motion.div
                initial={{ opacity: 0, y: -20 }}
                animate={{ opacity: 1, y: 0 }}
                exit={{ opacity: 0, y: -20 }}
                transition={{ duration: 0.3 }}
                className="p-4 flex flex-col gap-4"
            >
                {(() => {
                    const activeRegistration = sortedRegistrations.find(
                        (reg) => reg.id === activeTab
                    );

                    if (activeRegistration) {
                        return (
                            <>
                                <h2 className="text-2xl font-bold text-center">
                                    {translation["registration-details"] ||
                                        "Registration Details"}
                                </h2>

                                <div className="w-full flex flex-col sm:flex-row gap-4 items-start">
                                    <div className="max-w-full md:w-1/2">
                                        <FileUpload
                                            title={
                                                translation["payment-proof"] ||
                                                "Payment Proof"
                                            }
                                            name="payment_proof"
                                            required={false}
                                            onChange={() => {}} // Disable file upload
                                            errors={{}} // Disable errors
                                            disabled={true}
                                            value={
                                                activeRegistration.payment_proof ??
                                                ""
                                            }
                                        />
                                    </div>
                                    <div className="md:p-8 flex-grow">
                                        <p className="mb-2">
                                            <span className="font-semibold">
                                                {translation["status"] ||
                                                    "Status:"}
                                            </span>
                                            <span
                                                className={`ml-2 px-2 py-1 text-sm rounded-lg ${
                                                    activeRegistration.status ===
                                                    "approved"
                                                        ? "bg-green-200 text-green-800"
                                                        : activeRegistration.status ===
                                                          "pending"
                                                        ? "bg-yellow-200 text-yellow-800"
                                                        : "bg-red-200 text-red-800"
                                                }`}
                                            >
                                                {translation[
                                                    activeRegistration.status
                                                ] || activeRegistration.status}
                                            </span>
                                        </p>
                                        <p className="mb-2">
                                            <span className="font-semibold">
                                                {translation["submitted"] ||
                                                    "Submitted:"}
                                            </span>{" "}
                                            {new Date(
                                                activeRegistration.submitted_at ??
                                                    new Date()
                                            ).toLocaleString(locale, {
                                                day: "numeric",
                                                month: "short",
                                                year: "numeric",
                                                hour: "2-digit",
                                                minute: "2-digit",
                                                timeZoneName: "short",
                                            })}
                                        </p>
                                        <div className="flex flex-col gap-2 text-sm bg-gray-100 dark:bg-gray-800 p-3 rounded-lg">
                                            <span className="font-semibold">
                                                {translation["notes"] ||
                                                    "Notes:"}
                                            </span>
                                            <span>
                                                {activeRegistration.notes?.[
                                                    locale
                                                ] ||
                                                    translation[
                                                        "no-notes-provided"
                                                    ] ||
                                                    "No notes provided."}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </>
                        );
                    }

                    // if the team first time register
                    if (activeTab === "new") {
                        if (canRegister) {
                            return (
                                <form
                                    className="flex flex-col gap-4"
                                    onSubmit={handleRegisterTeam}
                                >
                                    <h2 className="text-2xl font-bold text-center">
                                        {translation["registration-form"] ||
                                            "Registration Form"}
                                    </h2>

                                    <p className="font-semibold">
                                        {`${(translation["registration-fee"] ||
                                            "Registration Fee")} : Rp ${team.competition.price.toLocaleString(
                                                locale
                                            )}`}
                                    </p>

                                    <PaymentMethodsCard
                                        paymentMethods={paymentMethods}
                                        translation={translation}
                                    />
                                    <FileUpload
                                        title={
                                            translation["payment-proof"] ||
                                            "Payment Proof"
                                        }
                                        name="payment_proof"
                                        onChange={(data) =>
                                            setData("payment_proof", data)
                                        }
                                        errors={errors}
                                        value={data.payment_proof}
                                        accept={{
                                            "image/*": [],
                                            "application/pdf": [],
                                        }}
                                        setIsUploadingFile={setIsUploadingFile}
                                    />
                                    <div className="md:col-span-full flex justify-center">
                                        <button
                                            type="submit"
                                            className="flex items-center justify-center gap-2 font-semibold cursor-pointer bg-blue-500 hover:bg-blue-600 text-white px-6 py-2 rounded-md"
                                            disabled={
                                                processing || isUploadingFile
                                            }
                                        >
                                            {processing || isUploadingFile ? (
                                                <>
                                                    <LoaderCircle className="animate-spin" />{" "}
                                                    {isUploadingFile
                                                        ? "Uploading File"
                                                        : translation[
                                                              "processing"
                                                          ] || "Processing"}
                                                </>
                                            ) : (
                                                translation["submit"] ||
                                                "Submit"
                                            )}
                                        </button>
                                    </div>
                                </form>
                            );
                        } else {
                            // check if any member has not filled out their profile
                            if (profileEmptyMembers.length > 0) {
                                return (
                                    <>
                                        <div className="flex items-center justify-between p-4 rounded-lg border border-yellow-600 bg-yellow-400/80 dark:bg-yellow-900/70">
                                            <div className="flex items-center gap-3">
                                                <TriangleAlert size={30} />
                                                <p className="text-sm">
                                                    {translation[
                                                        "profile-empty"
                                                    ] ||
                                                        "Some members have not filled out their profile. Please ask them to complete it before registering."}
                                                </p>
                                            </div>
                                        </div>

                                        <div className="flex flex-col gap-2 p-4">
                                            {profileEmptyMembers.map(
                                                (member) => (
                                                    <div
                                                        key={member.id}
                                                        className="flex items-center gap-3"
                                                    >
                                                        {member.avatar ? (
                                                            <img
                                                                src={
                                                                    member.avatar
                                                                }
                                                                width={32}
                                                                height={32}
                                                                className="rounded-full border-2 border-gray-800"
                                                            />
                                                        ) : (
                                                            <UserCircle
                                                                className="bg-white dark:bg-black rounded-full"
                                                                size={32}
                                                            />
                                                        )}
                                                        <p className="truncate text-sm capitalize">
                                                            {member.name}{" "}
                                                            {user.id ===
                                                                member.id &&
                                                                "(You)"}
                                                        </p>
                                                    </div>
                                                )
                                            )}
                                        </div>
                                    </>
                                );
                            }

                            // if the members not yet fulfill the minimum team size
                            if (
                                team.members.length <
                                team.competition.min_team_size
                            ) {
                                return (
                                    <>
                                        <h2 className="text-2xl font-bold text-center">
                                            {translation["team-not-ready"] ||
                                                "Your team is not yet ready for registration"}
                                        </h2>
                                        <p className="text-center">
                                            {translation[
                                                "team-not-ready-message"
                                            ]?.replace(
                                                ":min_team_size",
                                                `${team.competition.min_team_size}`
                                            ) ||
                                                `The team need at least ${team.competition.min_team_size} members to register`}
                                        </p>
                                    </>
                                );
                            }

                            return (
                                <>
                                    <h2 className="text-2xl font-bold text-center">
                                        {team.registrationStatus ===
                                        "not-started"
                                            ? translation[
                                                  "registration-not-started"
                                              ] || "Registration Not Started"
                                            : translation[
                                                  "registration-closed"
                                              ] || "Registration Closed"}
                                    </h2>
                                    <p className="text-center">
                                        {team.registrationStatus ===
                                        "not-started"
                                            ? translation[
                                                  "registration-not-started-message"
                                              ] ||
                                              "Registration for this competition has not started yet."
                                            : translation[
                                                  "registration-closed-message"
                                              ] ||
                                              "Registration for this competition has been closed."}
                                    </p>
                                </>
                            );
                        }
                    }

                    return (
                        <p className="text-center">
                            {translation["select-registration"] ||
                                "Select a registration to view details."}
                        </p>
                    );
                })()}
            </motion.div>
        </motion.div>
    );
}

function PaymentMethodsCard({
    paymentMethods,
    translation,
}: {
    paymentMethods: paymentMethods[];
    translation: Record<string, string>;
}) {
    const [activeIndex, setActiveIndex] = useState(
        paymentMethods.length > 0 ? 0 : null
    );
    const [copied, setCopied] = useState(false);

    const handleCopyToClipboard = (text: string) => {
        navigator.clipboard.writeText(text);
        setCopied(true);
        setTimeout(() => setCopied(false), 2000); // Reset after 2 seconds
    };

    return (
        <div className="flex flex-col gap-2">
            <label className="text-sm font-medium text-gray-700 dark:text-gray-300 capitalize">
                {translation["payment-methods"] || "Payment Methods"}
            </label>

            <div className="border border-gray-300 dark:border-gray-700 rounded-lg shadow-sm">
                <div className="flex items-center gap-1 p-2 border-b border-gray-200 dark:border-gray-700 flex-wrap">
                    {paymentMethods.map(({ method }, index) => (
                        <button
                            key={index}
                            type="button"
                            onClick={() => {
                                setCopied(false);
                                setActiveIndex(index);
                            }}
                            className={`cursor-pointer relative px-3 py-1.5 text-sm font-medium rounded-md transition-colors duration-200 focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-blue-500 dark:focus-visible:ring-offset-gray-900 ${
                                activeIndex === index
                                    ? "bg-blue-100 text-blue-800 dark:bg-blue-900/50 dark:text-blue-200"
                                    : "bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700"
                            }`}
                        >
                            {method}
                        </button>
                    ))}
                </div>

                <div className="p-3">
                    {activeIndex !== null ? (
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-2">
                            <div className="flex flex-col gap-2 p-2">
                                <label
                                    htmlFor="accountNumber"
                                    className="text-sm font-semibold text-gray-500 dark:text-gray-400 capitalize"
                                >
                                    {translation["account-number"] ||
                                        "Account Number"}
                                </label>

                                <div className="bg-gray-100 dark:bg-gray-800 flex items-center justify-between gap-2 rounded-md p-2">
                                    <span
                                        id="accountNumber"
                                        className="font-mono text-gray-700 dark:text-gray-300"
                                    >
                                        {
                                            paymentMethods[activeIndex]
                                                .accountNumber
                                        }
                                    </span>

                                    <button
                                        type="button"
                                        onClick={() =>
                                            handleCopyToClipboard(
                                                paymentMethods[activeIndex]
                                                    .accountNumber
                                            )
                                        }
                                        className="cursor-pointer rounded p-2 text-gray-500 hover:bg-gray-200 dark:text-gray-400 dark:hover:bg-gray-700"
                                    >
                                        {copied ? (
                                            <Check
                                                size={18}
                                                className="text-green-500"
                                            />
                                        ) : (
                                            <Copy size={18} />
                                        )}
                                    </button>
                                </div>
                            </div>
                            <div className="flex flex-col gap-2 p-2">
                                <label
                                    htmlFor="accountName"
                                    className="text-sm font-semibold text-gray-500 dark:text-gray-400 capitalize"
                                >
                                    {translation["account-name"] ||
                                        "Account Name"}
                                </label>

                                <div className="bg-gray-100 dark:bg-gray-800 flex items-center justify-between gap-2 rounded-md px-2 py-3">
                                    <span
                                        id="accountName"
                                        className="text-gray-700 dark:text-gray-300"
                                    >
                                        {paymentMethods[activeIndex].holderName}
                                    </span>
                                </div>
                            </div>
                        </div>
                    ) : (
                        <div className="text-center text-gray-500 dark:text-gray-400 py-4">
                            {translation["no-items-available"] ||
                                "No items available"}
                        </div>
                    )}
                </div>
            </div>
        </div>
    );
}

function SubmissionContent({
    translation,
    team,
    sortedRegistrations,
    locale,
}: {
    translation: Record<string, string>;
    team: TeamView;
    sortedRegistrations: TeamView["registrations"];
    locale: string;
}) {
    const [activeTab, setActiveTab] = useState<string | number>("new");
    const [isUploadingFile, setIsUploadingFile] = useState(false);
    const { post, processing, data, setData, errors, isDirty } =
        useForm<{
            submission: any;
        }>({
            submission: "",
        });
    const sortedSubmissions = useMemo(
        () =>
            team.submissions.sort(
                (a, b) =>
                    new Date(b.submitted_at ?? new Date()).getTime() -
                    new Date(a.submitted_at ?? new Date()).getTime()
            ),
        [team.submissions]
    );

    const handleSubmit = (e: React.FormEvent<HTMLFormElement>) => {
        e.preventDefault();
        if (!confirm('Are you sure you want to submit?')) return;
        post("", {
            preserveScroll: true,
        });
    };

    useEffect(() => {
         const handleBeforeUnload = (event: BeforeUnloadEvent) => {
            event.preventDefault();
            event.returnValue = ""; // Required for some older browsers
        };

        if (isDirty) {
            document.addEventListener("beforeunload", handleBeforeUnload);
        }

        return () => {
            document.removeEventListener("beforeunload", handleBeforeUnload);
        }
    }, [isDirty])

    return (
        <div className="flex flex-col gap-4">
            {/* navbar for switch between registrations */}
            <div className="max-w-full flex border-b border-gray-200 dark:border-gray-700 px-4 pt-4 overflow-auto">
                <button
                    onClick={() => setActiveTab("new")}
                    className={`cursor-pointer flex items-center gap-2 py-3 px-4 text-sm font-medium border-b-2 transition-colors ${
                        activeTab === "new"
                            ? "border-blue-500 text-blue-600 dark:text-blue-400"
                            : "border-transparent text-gray-500 hover:text-gray-700 dark:hover:text-gray-300"
                    }`}
                >
                    <PlusCircle size={16} />
                    {translation["submit"] || "Submit"}
                </button>

                {sortedSubmissions.length > 0 &&
                    sortedSubmissions.map((submission, index) => (
                        <button
                            key={submission.id}
                            onClick={() => setActiveTab(submission.id)}
                            className={`cursor-pointer py-3 px-4 text-sm font-medium border-b-2 transition-colors ${
                                activeTab === submission.id
                                    ? "border-blue-500 text-blue-600 dark:text-blue-400"
                                    : "border-transparent text-gray-500 hover:text-gray-700 dark:hover:text-gray-300"
                            }`}
                        >
                            {translation["history"] || "History"} #
                            {sortedSubmissions.length - index}
                        </button>
                    ))}
            </div>

            <div className="p-4 flex flex-col gap-4">
                {(() => {
                    const activeSubmission = sortedSubmissions.find(
                        (submission) => submission.id === activeTab
                    );

                    if (activeSubmission) {
                        return (
                            <>
                                <h2 className="text-2xl font-bold text-center">
                                    {translation["submission-details"] ||
                                        "Submission Details"}
                                </h2>

                                <div className="w-full flex flex-col md:flex-row gap-4 items-start">
                                    <div className="max-w-full md:w-1/2">
                                        <FileUpload
                                            title={
                                                translation["submission"] ||
                                                "Submission"
                                            }
                                            name="submission"
                                            required={false}
                                            onChange={() => {}} // Disable file upload
                                            errors={{}} // Disable errors
                                            disabled={true}
                                            value={
                                                activeSubmission.submission ??
                                                ""
                                            }
                                        />
                                    </div>

                                    <div className="md:p-8 flex-grow md:w-1/2">
                                        <p className="mb-2">
                                            <span className="font-semibold">
                                                {translation["status"] ||
                                                    "Status:"}
                                            </span>
                                            <span
                                                className={`ml-2 px-2 py-1 text-sm rounded-lg ${
                                                    activeSubmission.status ===
                                                    "reviewed"
                                                        ? "bg-green-200 text-green-800"
                                                        : "bg-yellow-200 text-yellow-800"
                                                }`}
                                            >
                                                {translation[
                                                    activeSubmission.status
                                                ] || activeSubmission.status}
                                            </span>
                                        </p>
                                        <p className="mb-2">
                                            <span className="font-semibold">
                                                {translation["submitted"] ||
                                                    "Submitted:"}
                                            </span>{" "}
                                            {new Date(
                                                activeSubmission.submitted_at ??
                                                    new Date()
                                            ).toLocaleString(locale, {
                                                day: "numeric",
                                                month: "short",
                                                year: "numeric",
                                                hour: "2-digit",
                                                minute: "2-digit",
                                                timeZoneName: "short",
                                            })}
                                        </p>

                                        <FileUpload
                                            title={
                                                translation["feedback"] ||
                                                "Feedback"
                                            }
                                            name="feedback"
                                            required={false}
                                            onChange={() => {}} // Disable file upload
                                            errors={{}} // Disable errors
                                            disabled={true}
                                            value={
                                                activeSubmission.feedback ?? ""
                                            }
                                        />
                                    </div>
                                </div>
                            </>
                        );
                    }

                    if (activeTab === "new") {
                        if (team.SubmissionStatus.canSubmit) {
                            return (
                                <form
                                    className="flex flex-col gap-4"
                                    onSubmit={handleSubmit}
                                >
                                    <h2 className="text-2xl font-bold text-center">
                                        {translation["submission-form"] ||
                                            "Submission Form"}
                                    </h2>

                                    <FileUpload
                                        title={
                                            translation["submision"] ||
                                            "Submision"
                                        }
                                        name="submission"
                                        onChange={(data) =>
                                            setData("submission", data)
                                        }
                                        maxSize={102400}
                                        errors={errors}
                                        value={data.submission}
                                        multipleFiles
                                        maxFiles={10}
                                        setIsUploadingFile={setIsUploadingFile}
                                    />

                                    <div className="md:col-span-full flex justify-center">
                                        <button
                                            type="submit"
                                            className="flex items-center justify-center gap-2 font-semibold cursor-pointer bg-blue-500 hover:bg-blue-600 text-white px-6 py-2 rounded-md"
                                            disabled={
                                                processing || isUploadingFile
                                            }
                                        >
                                            {processing || isUploadingFile ? (
                                                <>
                                                    <LoaderCircle className="animate-spin" />{" "}
                                                    {isUploadingFile
                                                        ? "Uploading File"
                                                        : translation[
                                                              "processing"
                                                          ] || "Processing"}
                                                </>
                                            ) : (
                                                translation["submit"] ||
                                                "Submit"
                                            )}
                                        </button>
                                    </div>
                                </form>
                            );
                        } else {
                            if (sortedRegistrations[0]?.status !== "approved") {
                                return (
                                    <>
                                        <h2 className="text-2xl font-bold text-center">
                                            {translation[
                                                "team-not-registered"
                                            ] ||
                                                "Your team is not registered yet."}
                                        </h2>
                                        <p className="text-center">
                                            {translation[
                                                "team-not-registered-message"
                                            ] ||
                                                "Please register your team first."}
                                        </p>
                                    </>
                                );
                            }

                            return (
                                <>
                                    <h2 className="text-2xl font-bold text-center">
                                        {translation["submission-closed"] ||
                                            "Submission Closed"}
                                    </h2>
                                    <p className="text-center">
                                        {translation[
                                            "submission-closed-message"
                                        ] ||
                                            "Submission for this competition has closed."}
                                    </p>
                                    {team.SubmissionStatus.nextOpenAt && (
                                        <p className="text-center font-semibold text-red-600 dark:text-red-500">
                                            {translation["next-open"] ||
                                                "Next open"}
                                            {": "}
                                            {new Date(
                                                team.SubmissionStatus.nextOpenAt
                                            ).toLocaleString(locale, {
                                                day: "numeric",
                                                month: "short",
                                                year: "numeric",
                                                hour: "2-digit",
                                                minute: "2-digit",
                                                timeZoneName: "short",
                                            })}
                                        </p>
                                    )}
                                </>
                            );
                        }
                    }

                    return (
                        <p className="text-center">
                            {translation["select-submission"] ||
                                "Select a submission to view details."}
                        </p>
                    );
                })()}
            </div>
        </div>
    );
}
