import FailedJobs from './FailedJobs';
import QueueStatus from './QueueStatus';

export default function QueuesPage() {
    return (
        <>
            <QueueStatus />
            <FailedJobs />
        </>
    );
}
