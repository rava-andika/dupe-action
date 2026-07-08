import MainLayout, { useTheme } from "@/Layouts/MainLayout";
import { ReactElement, useEffect, useState } from "react";
import KpiCard from "@/Components/DataVisual/KPICard";
import BarLineChart from "@/Components/DataVisual/BarLineChart";
import TextField from "@/Components/Form/TextField";
import { router, usePoll } from "@inertiajs/react";

interface Stat {
    current: number;
    previous: number | null;
}
interface DataChart {
    labels: string[];
    dataPoints: number[];
}

function Dashboard({
    stats,
    revenuePerMonth,
    usersPerMonth,
}: {
    stats: {
        revenue: Stat;
        users: Stat;
        verifiedTeams: Stat;
    };
    revenuePerMonth: DataChart;
    usersPerMonth: DataChart;
}) {
    const { darkMode } = useTheme();
    const query = Object.fromEntries(
        new URLSearchParams(window.location.search)
    );
    const [startDate, setStartDate] = useState<string>(query?.start_date || "");
    const [endDate, setEndDate] = useState<string>(query?.end_date || "");
    const today = new Date().toISOString().split("T")[0];

    useEffect(() => {
        const currentQuery = Object.fromEntries(
            new URLSearchParams(window.location.search)
        );

        if (startDate) {
            currentQuery.start_date = startDate;
        } else {
            delete currentQuery.start_date;
        }

        if (endDate) {
            currentQuery.end_date = endDate;
        } else {
            delete currentQuery.end_date;
        }

        if (
            startDate === (query?.start_date || "") &&
            endDate === (query?.end_date || "")
        ) {
            return;
        }

        router.get(window.location.pathname, currentQuery, {
            preserveScroll: true,
            replace: true,
        });
    }, [startDate, endDate]);

    // polling the data
    usePoll(10000);

    return (
        <div className="py-10 px-2 md:px-10 flex flex-col gap-10">
            <h1 className="text-3xl font-bold text-gray-900 dark:text-white mb-4 sm:mb-0">
                Dashboard Admin
            </h1>

            <div className="grid grid-cols-1 lg:grid-cols-2 gap-6 border-gray-300 bg-white dark:bg-gray-900 border dark:border-gray-600/50 p-5 rounded-xl">
                <TextField
                    title="Start Date"
                    type="date"
                    value={startDate}
                    onChange={setStartDate}
                    errors={{}}
                    required={false}
                    Fieldprops={{
                        max: endDate || today,
                    }}
                />
                <TextField
                    title="End Date"
                    type="date"
                    value={endDate}
                    onChange={setEndDate}
                    errors={{}}
                    required={false}
                    Fieldprops={{
                        min: startDate || today,
                        max: today,
                    }}
                />
            </div>

            {/* KPI Cards */}
            <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <KpiCard title="Revenue" data={stats.revenue} />
                <KpiCard title="Users" data={stats.users} />
                <KpiCard title="Verified Teams" data={stats.verifiedTeams} />
            </div>

            {/* Chart Section */}
            <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <BarLineChart
                    title="Revenue per month"
                    chartData={revenuePerMonth}
                    darkMode={darkMode}
                    legend="Revenue"
                    color="#4f46e5"
                />
                <BarLineChart
                    title="Users per month"
                    chartData={usersPerMonth}
                    darkMode={darkMode}
                    legend="Users"
                />
            </div>
        </div>
    );
}

Dashboard.layout = (page: ReactElement) => {
    return <MainLayout title="Admin Dashboard">{page}</MainLayout>;
};

export default Dashboard;
